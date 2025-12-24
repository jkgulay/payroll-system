import Dexie from "dexie";

// IndexedDB for offline storage
export const db = new Dexie("PayrollDB");

db.version(1).stores({
  // Sync queue for offline changes
  syncQueue: "++id, action, model_type, model_id, status, created_at, user_id",

  // Cached data from API
  employees:
    "id, employee_number, first_name, last_name, department_id, location_id, is_active",
  attendance: "id, employee_id, attendance_date, status",
  payrolls: "id, payroll_number, period_start_date, period_end_date, status",
  payrollItems: "id, payroll_id, employee_id",
  departments: "id, code, name, is_active",
  locations: "id, code, name, location_type, is_active",

  // Settings and metadata
  settings: "key, value",
  lastSync: "model_type, timestamp",
});

// Helper functions for common operations
export const dbHelpers = {
  // Cache employees locally
  async cacheEmployees(employees) {
    await db.employees.clear();
    await db.employees.bulkAdd(employees);
    await db.lastSync.put({ model_type: "employees", timestamp: new Date() });
  },

  // Get cached employees
  async getCachedEmployees() {
    return await db.employees.toArray();
  },

  // Cache attendance records
  async cacheAttendance(attendance) {
    await db.attendance.bulkPut(attendance);
    await db.lastSync.put({ model_type: "attendance", timestamp: new Date() });
  },

  // Get cached attendance for date range
  async getCachedAttendance(startDate, endDate) {
    return await db.attendance
      .where("attendance_date")
      .between(startDate, endDate, true, true)
      .toArray();
  },

  // Cache payrolls
  async cachePayrolls(payrolls) {
    await db.payrolls.bulkPut(payrolls);
    await db.lastSync.put({ model_type: "payrolls", timestamp: new Date() });
  },

  // Get cached payrolls
  async getCachedPayrolls() {
    return await db.payrolls.orderBy("period_start_date").reverse().toArray();
  },

  // Clear all cached data
  async clearCache() {
    await db.employees.clear();
    await db.attendance.clear();
    await db.payrolls.clear();
    await db.payrollItems.clear();
    await db.departments.clear();
    await db.locations.clear();
    await db.lastSync.clear();
  },

  // Get last sync time for model
  async getLastSync(modelType) {
    const record = await db.lastSync.get(modelType);
    return record?.timestamp || null;
  },

  // Add to sync queue
  async addToSyncQueue(action, modelType, modelId, data, userId) {
    return await db.syncQueue.add({
      action,
      model_type: modelType,
      model_id: modelId,
      data,
      status: "pending",
      attempts: 0,
      created_at: new Date(),
      user_id: userId,
    });
  },

  // Get pending sync items
  async getPendingSyncItems() {
    return await db.syncQueue.where("status").equals("pending").toArray();
  },

  // Clear completed sync items
  async clearCompletedSync() {
    await db.syncQueue.where("status").equals("completed").delete();
  },
};

export default db;
