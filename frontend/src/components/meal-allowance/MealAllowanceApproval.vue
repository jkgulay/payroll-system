<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="600px"
    persistent
  >
    <v-card v-if="mealAllowance">
      <v-card-title class="bg-primary">
        <v-icon left>mdi-check-circle</v-icon>
        Approve/Reject Allowance
        <v-spacer></v-spacer>
        <v-btn icon="mdi-close" variant="text" @click="close"></v-btn>
      </v-card-title>

      <v-card-text class="pt-4">
        <v-alert type="info" class="mb-4">
          <strong>{{ mealAllowance.reference_number }}</strong
          ><br />
          {{ mealAllowance.title }}
        </v-alert>

        <v-form ref="formRef">
          <v-row>
            <v-col cols="12">
              <v-text-field
                v-model="form.prepared_by_name"
                label="Prepared By"
                outlined
                dense
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="form.checked_by_name"
                label="Checked By"
                outlined
                dense
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="form.verified_by_name"
                label="Checked & Verified By"
                outlined
                dense
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="form.recommended_by_name"
                label="Recommended By"
                outlined
                dense
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="form.approved_by_name"
                label="Approved By"
                outlined
                dense
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-textarea
                v-model="form.approval_notes"
                label="Notes (Optional)"
                outlined
                dense
                rows="3"
              ></v-textarea>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn @click="close">Cancel</v-btn>
        <v-btn color="error" @click="reject" :loading="processing">
          Reject
        </v-btn>
        <v-btn color="success" @click="approve" :loading="processing">
          Approve & Generate PDF
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref } from "vue";
import { useToast } from "vue-toastification";
import mealAllowanceService from "@/services/mealAllowanceService";
import { devLog } from "@/utils/devLog";
import { useConfirmDialog } from "@/composables/useConfirmDialog";

const toast = useToast();
const { confirm: confirmDialog } = useConfirmDialog();

const props = defineProps({
  modelValue: Boolean,
  mealAllowance: Object,
});

const emit = defineEmits(["update:modelValue", "approved"]);

const formRef = ref(null);
const processing = ref(false);

const form = ref({
  prepared_by_name: "",
  checked_by_name: "",
  verified_by_name: "",
  recommended_by_name: "",
  approved_by_name: "",
  approval_notes: "",
});

async function approve() {
  processing.value = true;
  try {
    // First approve the allowance
    await mealAllowanceService.updateApproval(
      props.mealAllowance.id,
      "approve",
      form.value.approval_notes,
      {
        prepared_by_name: form.value.prepared_by_name,
        checked_by_name: form.value.checked_by_name,
        verified_by_name: form.value.verified_by_name,
        recommended_by_name: form.value.recommended_by_name,
        approved_by_name: form.value.approved_by_name,
      },
    );

    // Then generate PDF
    await mealAllowanceService.generatePdf(props.mealAllowance.id);

    toast.success("Allowance approved and PDF generated successfully");
    emit("approved");
    close();
  } catch (error) {
    devLog.error("Error approving allowance:", error);
    toast.error("Failed to approve allowance");
  } finally {
    processing.value = false;
  }
}

async function reject() {
  if (!(await confirmDialog("Are you sure you want to reject this allowance?")))
    return;

  processing.value = true;
  try {
    await mealAllowanceService.updateApproval(
      props.mealAllowance.id,
      "reject",
      form.value.approval_notes,
    );

    toast.success("Allowance rejected");
    emit("approved");
    close();
  } catch (error) {
    devLog.error("Error rejecting allowance:", error);
    toast.error("Failed to reject allowance");
  } finally {
    processing.value = false;
  }
}

function close() {
  emit("update:modelValue", false);
  form.value = {
    prepared_by_name: "",
    checked_by_name: "",
    verified_by_name: "",
    recommended_by_name: "",
    approved_by_name: "",
    approval_notes: "",
  };
}
</script>
