import { defineStore } from "pinia";
import { ref } from "vue";
import { db } from "@/services/db";
import api from "@/services/api";

export const useSyncStore = defineStore("sync", () => {
  // State
  const isSyncing = ref(false);
  const isOnline = ref(navigator.onLine);
  const pendingChanges = ref(0);
  const lastSyncTime = ref(null);
  const syncInterval = ref(null);

  // Actions
  async function startSync() {
    if (syncInterval.value) return;

    // Sync immediately
    await syncNow();

    // Set up interval sync (every 5 minutes)
    syncInterval.value = setInterval(async () => {
      await syncNow();
    }, 5 * 60 * 1000);
  }

  function stopSync() {
    if (syncInterval.value) {
      clearInterval(syncInterval.value);
      syncInterval.value = null;
    }
  }

  async function syncNow() {
    if (isSyncing.value || !isOnline.value) return;

    isSyncing.value = true;
    try {
      // Get pending changes from IndexedDB
      const pending = await db.syncQueue
        .where("status")
        .equals("pending")
        .toArray();
      pendingChanges.value = pending.length;

      // Process each pending change
      for (const change of pending) {
        try {
          await processChange(change);

          // Mark as completed
          await db.syncQueue.update(change.id, {
            status: "completed",
            processed_at: new Date(),
          });

          pendingChanges.value--;
        } catch (error) {
          console.error("Sync error:", error);

          // Mark as failed and increment attempts
          await db.syncQueue.update(change.id, {
            status: "failed",
            attempts: (change.attempts || 0) + 1,
            error_message: error.message,
          });
        }
      }

      lastSyncTime.value = new Date();
    } catch (error) {
      console.error("Sync process error:", error);
    } finally {
      isSyncing.value = false;
    }
  }

  async function processChange(change) {
    const { action, model_type, model_id, data } = change;

    switch (action) {
      case "create":
        await api.post(`/${model_type}`, data);
        break;
      case "update":
        await api.put(`/${model_type}/${model_id}`, data);
        break;
      case "delete":
        await api.delete(`/${model_type}/${model_id}`);
        break;
    }
  }

  async function queueChange(action, modelType, modelId, data) {
    const change = {
      action,
      model_type: modelType,
      model_id: modelId,
      data,
      status: "pending",
      attempts: 0,
      created_at: new Date(),
      user_id: null, // Will be set from auth store
    };

    await db.syncQueue.add(change);
    pendingChanges.value++;

    // Try to sync immediately if online
    if (isOnline.value && !isSyncing.value) {
      await syncNow();
    }
  }

  function updateOnlineStatus(status) {
    isOnline.value = status;

    if (status) {
      startSync();
    } else {
      stopSync();
    }
  }

  return {
    // State
    isSyncing,
    isOnline,
    pendingChanges,
    lastSyncTime,
    // Actions
    startSync,
    stopSync,
    syncNow,
    queueChange,
    updateOnlineStatus,
  };
});
