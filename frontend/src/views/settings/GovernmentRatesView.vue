<template>
  <div class="gov-rates-page">
    <v-overlay :model-value="loading" class="align-center justify-center">
      <v-progress-circular
        indeterminate
        color="#ED985F"
        :size="70"
        :width="7"
      ></v-progress-circular>
    </v-overlay>

    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="back-button-wrapper">
          <button class="back-button" @click="goBack">
            <v-icon size="20">mdi-arrow-left</v-icon>
            <span>{{ isAdmin ? "Back to Settings" : "Back" }}</span>
          </button>
        </div>

        <div class="header-main">
          <div class="page-title-section">
            <div class="page-icon-badge">
              <v-icon size="22">mdi-bank</v-icon>
            </div>
            <div>
              <h1 class="page-title">Government Rates</h1>
              <p class="page-subtitle">
                Manage contribution tables and customize employee deductions
              </p>
            </div>
          </div>

          <div class="action-buttons" v-if="hasAccess">
            <button class="action-btn action-btn-secondary" @click="loadRates">
              <v-icon size="20">mdi-refresh</v-icon>
              <span>Refresh</span>
            </button>
            <button
              class="action-btn action-btn-secondary"
              @click="switchTab('calculator')"
            >
              <v-icon size="20">mdi-calculator-variant</v-icon>
              <span>Calculator</span>
            </button>
            <button
              v-if="activeTab !== 'employees' && activeTab !== 'calculator'"
              class="action-btn action-btn-primary"
              @click="startAdd(activeTab)"
            >
              <v-icon size="20">mdi-plus</v-icon>
              <span>Add Bracket</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Access Gate for Payrollist -->
    <template v-if="!isAdminOrHr && !hasAccess">
      <!-- No request yet -->
      <v-alert
        v-if="accessStatus === 'none'"
        type="info"
        variant="tonal"
        prominent
        class="ma-4"
        icon="mdi-lock-outline"
      >
        <v-alert-title>Access Required</v-alert-title>
        <p class="mt-1">
          You need to request access from an administrator before you can manage
          government rates.
        </p>
        <v-btn
          color="primary"
          variant="flat"
          class="mt-3"
          prepend-icon="mdi-send"
          @click="requestDialog = true"
        >
          Request Access
        </v-btn>
      </v-alert>

      <!-- Pending -->
      <v-alert
        v-else-if="accessStatus === 'pending'"
        type="warning"
        variant="tonal"
        prominent
        class="ma-4"
        icon="mdi-clock-outline"
      >
        <v-alert-title>Pending Approval</v-alert-title>
        <p class="mt-1">
          Your access request is pending admin approval. You will be able to
          manage government rates once approved.
        </p>
      </v-alert>

      <!-- Rejected -->
      <v-alert
        v-else-if="accessStatus === 'rejected'"
        type="error"
        variant="tonal"
        prominent
        class="ma-4"
        icon="mdi-close-circle-outline"
      >
        <v-alert-title>Request Rejected</v-alert-title>
        <p class="mt-1">{{ accessMessage }}</p>
        <v-btn
          color="primary"
          variant="flat"
          class="mt-3"
          prepend-icon="mdi-send"
          @click="requestDialog = true"
        >
          Submit New Request
        </v-btn>
      </v-alert>

      <!-- My Requests History -->
      <v-expansion-panels v-model="requestsPanel" class="mx-4 mb-4">
        <v-expansion-panel>
          <v-expansion-panel-title>
            <v-icon class="mr-2">mdi-history</v-icon>
            My Access Requests
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <v-list v-if="myRequests.length > 0" density="compact">
              <v-list-item
                v-for="req in myRequests"
                :key="req.id"
                :subtitle="req.reason"
              >
                <template v-slot:prepend>
                  <v-icon :color="getRequestStatusColor(req.status)">
                    {{
                      req.status === "pending"
                        ? "mdi-clock-outline"
                        : req.status === "approved"
                          ? "mdi-check-circle"
                          : "mdi-close-circle"
                    }}
                  </v-icon>
                </template>
                <template v-slot:append>
                  <v-chip
                    :color="getRequestStatusColor(req.status)"
                    size="x-small"
                    variant="flat"
                  >
                    {{ req.status }}
                  </v-chip>
                </template>
              </v-list-item>
            </v-list>
            <p v-else class="text-center text-medium-emphasis py-4">
              No requests yet.
            </p>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>

      <!-- Request Access Dialog -->
      <v-dialog v-model="requestDialog" max-width="500" persistent>
        <v-card rounded="lg">
          <v-card-title class="d-flex align-center pa-4">
            <v-icon color="primary" class="mr-2">mdi-lock-open-variant</v-icon>
            Request Government Rates Access
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-4">
            <p class="text-body-2 mb-4">
              Please provide a reason for needing access to the Government Rates
              module.
            </p>
            <v-textarea
              v-model="requestReason"
              label="Reason"
              variant="outlined"
              rows="3"
              :rules="[(v) => !!v || 'Reason is required']"
              placeholder="Explain why you need access to manage government rates"
            ></v-textarea>
          </v-card-text>
          <v-divider></v-divider>
          <v-card-actions class="pa-4">
            <v-spacer></v-spacer>
            <v-btn
              variant="text"
              @click="
                requestDialog = false;
                requestReason = '';
              "
            >
              Cancel
            </v-btn>
            <v-btn
              color="primary"
              variant="flat"
              :loading="submittingRequest"
              :disabled="!requestReason"
              prepend-icon="mdi-send"
              @click="submitAccessRequest"
            >
              Submit Request
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </template>

    <!-- Main Content (only when access granted) -->
    <template v-if="hasAccess">
      <!-- Stats Cards -->
      <div class="stats-grid">
        <div
          class="stat-card"
          :class="{ active: activeTab === 'employees' }"
          @click="switchTab('employees')"
        >
          <div class="stat-icon employees">
            <v-icon size="20">mdi-account-group</v-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">Employee Overrides</div>
            <div class="stat-value">Custom</div>
          </div>
        </div>
        <div
          v-for="rt in rateTabs"
          :key="rt.value"
          class="stat-card"
          :class="{ active: activeTab === rt.value }"
          @click="switchTab(rt.value)"
        >
          <div class="stat-icon" :class="rt.value">
            <v-icon size="20">{{ rt.icon }}</v-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">{{ rt.label }} Brackets</div>
            <div
              class="stat-value"
              :class="{ success: rates[rt.value].length }"
            >
              {{ rates[rt.value].length || "Fallback" }}
            </div>
          </div>
        </div>
        <div
          class="stat-card"
          :class="{ active: activeTab === 'calculator' }"
          @click="switchTab('calculator')"
        >
          <div class="stat-icon calculator">
            <v-icon size="20">mdi-calculator-variant</v-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">Calculator</div>
            <div class="stat-value">Compute</div>
          </div>
        </div>
      </div>

      <!-- Content Card -->
      <div class="content-card">
        <!-- ──── Employee Contributions ──── -->
        <div v-if="activeTab === 'employees'">
          <employee-contributions-tab ref="employeeContributionsRef" />
        </div>

        <!-- ──── Rate Tables (SSS / PhilHealth / PagIBIG / Tax) ──── -->
        <div v-for="rt in rateTabs" :key="rt.value">
          <div v-if="activeTab === rt.value" class="rate-panel">
            <!-- Info Banner -->
            <div class="rate-info-banner" :class="rt.value">
              <v-icon size="18" class="mr-2">{{ rt.icon }}</v-icon>
              <span>{{ rt.description }}</span>
              <span class="rate-info-note">{{
                currentRates(rt.value).length
                  ? `${currentRates(rt.value).length} bracket(s) configured`
                  : "Using built-in fallback table"
              }}</span>
            </div>

            <!-- Toolbar -->
            <div class="rate-toolbar">
              <v-btn
                v-if="!showForm || editingForTab !== rt.value"
                :color="rt.color"
                variant="tonal"
                size="small"
                prepend-icon="mdi-plus"
                @click="startAdd(rt.value)"
              >
                Add Bracket
              </v-btn>
              <v-spacer />
              <v-btn
                v-if="currentRates(rt.value).length > 0"
                color="error"
                variant="text"
                size="small"
                prepend-icon="mdi-delete-sweep"
                @click="clearTabRates(rt.value)"
                :disabled="loading"
              >
                Clear All
              </v-btn>
            </div>

            <!-- ──── Inline Add/Edit Form ──── -->
            <v-expand-transition>
              <div
                v-if="showForm && editingForTab === rt.value"
                class="form-panel"
              >
                <div class="form-panel-header">
                  <div class="form-panel-title">
                    <v-icon size="18" class="mr-2" :color="rt.color">{{
                      editingRate ? "mdi-pencil" : "mdi-plus-circle"
                    }}</v-icon>
                    {{ editingRate ? "Edit" : "New" }} {{ rt.label }} Bracket
                  </div>
                  <v-btn
                    icon="mdi-close"
                    size="small"
                    variant="text"
                    @click="cancelForm"
                  />
                </div>

                <v-form ref="formRef" v-model="formValid" class="pa-4">
                  <v-row dense>
                    <v-col cols="12" md="4">
                      <v-text-field
                        v-model="form.name"
                        label="Bracket Name *"
                        :rules="[rules.required]"
                        variant="outlined"
                        density="compact"
                        placeholder="e.g., SSS Bracket 1"
                        hide-details="auto"
                      />
                    </v-col>
                    <v-col cols="6" md="2">
                      <v-text-field
                        v-model.number="form.min_salary"
                        label="Min Salary *"
                        type="number"
                        prefix="₱"
                        :rules="[rules.requiredNumeric]"
                        variant="outlined"
                        density="compact"
                        hide-details="auto"
                      />
                    </v-col>
                    <v-col cols="6" md="2">
                      <v-text-field
                        v-model.number="form.max_salary"
                        label="Max Salary"
                        type="number"
                        prefix="₱"
                        variant="outlined"
                        density="compact"
                        hint="Empty = no limit"
                        hide-details="auto"
                      />
                    </v-col>
                    <v-col cols="6" md="2">
                      <v-text-field
                        v-model="form.effective_date"
                        label="Effective Date *"
                        type="date"
                        :rules="[rules.required]"
                        variant="outlined"
                        density="compact"
                        hide-details="auto"
                      />
                    </v-col>
                    <v-col cols="6" md="2">
                      <v-text-field
                        v-model="form.end_date"
                        label="End Date"
                        type="date"
                        variant="outlined"
                        density="compact"
                        hide-details="auto"
                      />
                    </v-col>
                  </v-row>

                  <v-row dense class="mt-1">
                    <v-col cols="6" md="3">
                      <v-text-field
                        v-model.number="form.employee_rate"
                        label="Employee Rate (%)"
                        type="number"
                        suffix="%"
                        :rules="[rules.rateRange]"
                        variant="outlined"
                        density="compact"
                        :disabled="!!form.employee_fixed"
                        hide-details="auto"
                      />
                    </v-col>
                    <v-col cols="6" md="3">
                      <v-text-field
                        v-model.number="form.employee_fixed"
                        label="Employee Fixed (₱)"
                        type="number"
                        prefix="₱"
                        variant="outlined"
                        density="compact"
                        :disabled="!!form.employee_rate"
                        hide-details="auto"
                      />
                    </v-col>
                    <v-col cols="6" md="3">
                      <v-text-field
                        v-model.number="form.employer_rate"
                        label="Employer Rate (%)"
                        type="number"
                        suffix="%"
                        :rules="[rules.rateRange]"
                        variant="outlined"
                        density="compact"
                        :disabled="!!form.employer_fixed"
                        hide-details="auto"
                      />
                    </v-col>
                    <v-col cols="6" md="3">
                      <v-text-field
                        v-model.number="form.employer_fixed"
                        label="Employer Fixed (₱)"
                        type="number"
                        prefix="₱"
                        variant="outlined"
                        density="compact"
                        :disabled="!!form.employer_rate"
                        hide-details="auto"
                      />
                    </v-col>
                  </v-row>

                  <v-row dense class="mt-1">
                    <v-col cols="12" md="3">
                      <v-text-field
                        v-model.number="form.total_contribution"
                        label="Total Contribution (₱)"
                        type="number"
                        prefix="₱"
                        variant="outlined"
                        density="compact"
                        hint="Overrides computed total"
                        hide-details="auto"
                      />
                    </v-col>
                    <v-col cols="12" md="5">
                      <v-text-field
                        v-model="form.notes"
                        label="Notes"
                        variant="outlined"
                        density="compact"
                        hide-details="auto"
                      />
                    </v-col>
                    <v-col
                      cols="12"
                      md="2"
                      class="d-flex align-center justify-center"
                    >
                      <v-switch
                        v-model="form.is_active"
                        label="Active"
                        color="primary"
                        density="compact"
                        hide-details
                      />
                    </v-col>
                    <v-col
                      cols="12"
                      md="2"
                      class="d-flex align-center justify-end ga-2"
                    >
                      <v-btn
                        variant="text"
                        size="small"
                        @click="cancelForm"
                        :disabled="saving"
                      >
                        Cancel
                      </v-btn>
                      <v-btn
                        :color="rt.color"
                        variant="elevated"
                        size="small"
                        :loading="saving"
                        @click="saveRate(rt.value)"
                        prepend-icon="mdi-content-save"
                      >
                        {{ editingRate ? "Update" : "Save" }}
                      </v-btn>
                    </v-col>
                  </v-row>
                </v-form>
              </div>
            </v-expand-transition>

            <!-- ──── Rates Table ──── -->
            <div
              v-if="currentRates(rt.value).length > 0"
              class="rates-table-wrap"
            >
              <v-table density="compact" class="rates-table">
                <thead>
                  <tr>
                    <th>Bracket</th>
                    <th class="text-right">Min Salary</th>
                    <th class="text-right">Max Salary</th>
                    <th class="text-right">Employee</th>
                    <th class="text-right">Employer</th>
                    <th class="text-right">Total</th>
                    <th>Effective</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width: 80px">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="rate in currentRates(rt.value)"
                    :key="rate.id"
                    :class="{ 'row-inactive': !rate.is_active }"
                  >
                    <td>
                      <div class="font-weight-medium text-body-2">
                        {{ rate.name }}
                      </div>
                      <div
                        v-if="rate.notes"
                        class="text-caption text-medium-emphasis"
                      >
                        {{ rate.notes }}
                      </div>
                    </td>
                    <td class="text-right mono">
                      ₱{{ formatCurrency(rate.min_salary) }}
                    </td>
                    <td class="text-right mono">
                      <template v-if="rate.max_salary"
                        >₱{{ formatCurrency(rate.max_salary) }}</template
                      >
                      <span v-else class="text-medium-emphasis text-caption"
                        >No limit</span
                      >
                    </td>
                    <td class="text-right">
                      <v-chip
                        v-if="rate.employee_rate"
                        size="x-small"
                        color="blue"
                        variant="tonal"
                        >{{ rate.employee_rate }}%</v-chip
                      >
                      <span
                        v-else-if="rate.employee_fixed"
                        class="mono text-blue"
                        >₱{{ formatCurrency(rate.employee_fixed) }}</span
                      >
                      <span v-else class="text-medium-emphasis">—</span>
                    </td>
                    <td class="text-right">
                      <v-chip
                        v-if="rate.employer_rate"
                        size="x-small"
                        color="green"
                        variant="tonal"
                        >{{ rate.employer_rate }}%</v-chip
                      >
                      <span
                        v-else-if="rate.employer_fixed"
                        class="mono text-green"
                        >₱{{ formatCurrency(rate.employer_fixed) }}</span
                      >
                      <span v-else class="text-medium-emphasis">—</span>
                    </td>
                    <td class="text-right">
                      <span
                        v-if="rate.total_contribution"
                        class="font-weight-bold mono"
                        >₱{{ formatCurrency(rate.total_contribution) }}</span
                      >
                      <span v-else class="text-caption text-medium-emphasis"
                        >Auto</span
                      >
                    </td>
                    <td>
                      <div class="text-caption">
                        {{ formatDate(rate.effective_date) }}
                        <template v-if="rate.end_date">
                          → {{ formatDate(rate.end_date) }}
                        </template>
                      </div>
                    </td>
                    <td class="text-center">
                      <v-chip
                        :color="rate.is_active ? 'success' : 'default'"
                        size="x-small"
                        variant="tonal"
                        class="status-chip"
                        @click="toggleActive(rate)"
                      >
                        {{ rate.is_active ? "Active" : "Inactive" }}
                      </v-chip>
                    </td>
                    <td class="text-center">
                      <v-btn
                        icon
                        size="x-small"
                        variant="text"
                        color="primary"
                        @click="startEdit(rate)"
                        title="Edit"
                      >
                        <v-icon size="16">mdi-pencil</v-icon>
                      </v-btn>
                      <v-btn
                        icon
                        size="x-small"
                        variant="text"
                        color="error"
                        @click="deleteRate(rate)"
                        title="Delete"
                      >
                        <v-icon size="16">mdi-delete</v-icon>
                      </v-btn>
                    </td>
                  </tr>
                </tbody>
              </v-table>
            </div>

            <!-- Empty state -->
            <div
              v-else-if="!showForm || editingForTab !== rt.value"
              class="empty-state"
            >
              <v-icon size="52" :color="rt.color" class="empty-icon">{{
                rt.icon
              }}</v-icon>
              <p class="text-body-1 mt-3 font-weight-medium">
                No {{ rt.label }} brackets configured
              </p>
              <p class="text-caption text-medium-emphasis mb-3">
                The system uses built-in {{ rt.label }} contribution tables as
                fallback. Add custom brackets to override them.
              </p>
              <v-btn
                :color="rt.color"
                variant="tonal"
                size="small"
                prepend-icon="mdi-plus"
                @click="startAdd(rt.value)"
              >
                Add First Bracket
              </v-btn>
            </div>
          </div>
        </div>

        <!-- ──── Calculator ──── -->
        <div v-if="activeTab === 'calculator'" class="calculator-panel">
          <div class="calc-header">
            <v-icon size="24" color="primary" class="mr-2"
              >mdi-calculator-variant</v-icon
            >
            <div>
              <div class="text-h6 font-weight-bold">
                Contribution Calculator
              </div>
              <div class="text-caption text-medium-emphasis">
                Enter a monthly salary to see the computed government
                contributions per cutoff
              </div>
            </div>
          </div>

          <div class="calc-input-row">
            <v-text-field
              v-model.number="calcSalary"
              label="Monthly Basic Salary"
              type="number"
              prefix="₱"
              variant="outlined"
              density="comfortable"
              hide-details
              class="calc-input"
              @keyup.enter="computeContributions"
            />
            <v-btn
              color="primary"
              variant="elevated"
              size="large"
              @click="computeContributions"
              :loading="calcLoading"
              prepend-icon="mdi-calculator"
            >
              Compute
            </v-btn>
          </div>

          <v-expand-transition>
            <div v-if="calcResult" class="calc-results">
              <div class="calc-salary-label">
                Monthly Salary:
                <strong>₱{{ formatCurrency(calcResult.salary) }}</strong>
              </div>

              <div class="calc-cards">
                <!-- SSS -->
                <div class="calc-card calc-sss">
                  <div class="calc-card-icon">
                    <v-icon size="22" color="white">mdi-shield-account</v-icon>
                  </div>
                  <div class="calc-card-body">
                    <div class="calc-card-title">SSS</div>
                    <div class="calc-card-row">
                      <span>Employee share</span>
                      <span class="mono"
                        >₱{{
                          formatCurrency(calcResult.sss?.employee_share || 0)
                        }}</span
                      >
                    </div>
                    <div class="calc-card-row">
                      <span>Employer share</span>
                      <span class="mono"
                        >₱{{
                          formatCurrency(calcResult.sss?.employer_share || 0)
                        }}</span
                      >
                    </div>
                    <v-divider class="my-1" />
                    <div class="calc-card-row highlight">
                      <span>Exact value used in payroll</span>
                      <span class="mono font-weight-bold"
                        >₱{{
                          formatCurrency(calcResult.sss?.employee_share || 0)
                        }}</span
                      >
                    </div>
                  </div>
                </div>

                <!-- PhilHealth -->
                <div class="calc-card calc-ph">
                  <div class="calc-card-icon">
                    <v-icon size="22" color="white">mdi-hospital-box</v-icon>
                  </div>
                  <div class="calc-card-body">
                    <div class="calc-card-title">PhilHealth</div>
                    <div class="calc-card-row">
                      <span>Employee share</span>
                      <span class="mono"
                        >₱{{
                          formatCurrency(
                            calcResult.philhealth?.employee_share || 0,
                          )
                        }}</span
                      >
                    </div>
                    <div class="calc-card-row">
                      <span>Employer share</span>
                      <span class="mono"
                        >₱{{
                          formatCurrency(
                            calcResult.philhealth?.employer_share || 0,
                          )
                        }}</span
                      >
                    </div>
                    <v-divider class="my-1" />
                    <div class="calc-card-row highlight">
                      <span>Exact value used in payroll</span>
                      <span class="mono font-weight-bold"
                        >₱{{
                          formatCurrency(
                            calcResult.philhealth?.employee_share || 0,
                          )
                        }}</span
                      >
                    </div>
                  </div>
                </div>

                <!-- Pag-IBIG -->
                <div class="calc-card calc-pi">
                  <div class="calc-card-icon">
                    <v-icon size="22" color="white">mdi-home-city</v-icon>
                  </div>
                  <div class="calc-card-body">
                    <div class="calc-card-title">Pag-IBIG</div>
                    <div class="calc-card-row">
                      <span>Employee share</span>
                      <span class="mono"
                        >₱{{
                          formatCurrency(
                            calcResult.pagibig?.employee_share || 0,
                          )
                        }}</span
                      >
                    </div>
                    `
                    <div class="calc-card-row">
                      <span>Employer share</span>
                      <span class="mono"
                        >₱{{
                          formatCurrency(
                            calcResult.pagibig?.employer_share || 0,
                          )
                        }}</span
                      >
                    </div>
                    <v-divider class="my-1" />
                    <div class="calc-card-row highlight">
                      <span>Exact value used in payroll</span>
                      <span class="mono font-weight-bold"
                        >₱{{
                          formatCurrency(
                            calcResult.pagibig?.employee_share || 0,
                          )
                        }}</span
                      >
                    </div>
                  </div>
                </div>
              </div>

              <!-- Total Summary -->
              <div class="calc-total-bar">
                <div class="calc-total-label">
                  <v-icon size="18" class="mr-2">mdi-sigma</v-icon>
                  Total Employee Deduction Per Cutoff
                </div>
                <div class="calc-total-value">
                  ₱{{
                    formatCurrency(
                      (calcResult.total_employee_deductions || 0) / 2,
                    )
                  }}
                </div>
              </div>

              <div class="calc-note">
                <v-icon size="14" class="mr-1">mdi-information-outline</v-icon>
                These are the computed defaults. If an employee has a custom
                override set in the Employee Contributions tab, that value is
                used instead.
              </div>
            </div>
          </v-expand-transition>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import EmployeeContributionsTab from "@/components/settings/EmployeeContributionsTab.vue";
import { devLog } from "@/utils/devLog";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { formatCurrency, formatDate } from "@/utils/formatters";
import { useAuthStore } from "@/stores/auth";
import moduleAccessService from "@/services/moduleAccessService";

const toast = useToast();
const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const { confirm: confirmDialog } = useConfirmDialog();

const isAdmin = computed(() => authStore.userRole === "admin");
const isAdminOrHr = computed(() =>
  ["admin", "hr"].includes(authStore.userRole),
);
const userRole = computed(() => authStore.userRole);

const goBack = () => {
  if (isAdmin.value) {
    router.push("/settings");
  } else {
    router.back();
  }
};

// Access control
const accessStatus = ref("none");
const accessMessage = ref("");
const requestDialog = ref(false);
const requestReason = ref("");
const submittingRequest = ref(false);
const myRequests = ref([]);
const requestsPanel = ref(null);
const hasAccess = computed(
  () =>
    isAdminOrHr.value ||
    accessStatus.value === "approved" ||
    accessStatus.value === "admin",
);

const getRequestStatusColor = (status) => {
  const colors = { pending: "warning", approved: "success", rejected: "error" };
  return colors[status] || "grey";
};

const checkGovRatesAccess = async () => {
  if (isAdminOrHr.value) return;
  try {
    const response = await moduleAccessService.checkAccess("government-rates");
    accessStatus.value = response.status;
    accessMessage.value = response.message || "";
  } catch {
    accessStatus.value = "none";
  }
};

const loadMyRequests = async () => {
  if (isAdminOrHr.value) return;
  try {
    const response = await moduleAccessService.getRequests("government-rates");
    myRequests.value = response.data || [];
  } catch {
    myRequests.value = [];
  }
};

const submitAccessRequest = async () => {
  if (!requestReason.value) return;
  submittingRequest.value = true;
  try {
    await moduleAccessService.submitRequest("government-rates", {
      reason: requestReason.value,
    });
    toast.success("Access request submitted successfully");
    requestDialog.value = false;
    requestReason.value = "";
    accessStatus.value = "pending";
    await loadMyRequests();
  } catch (error) {
    const msg = error.response?.data?.message || "Failed to submit request";
    toast.error(msg);
  } finally {
    submittingRequest.value = false;
  }
};

// ─── State ────────────────────────────────────────────────────────────────
const activeTab = ref("employees");
const loading = ref(false);
const saving = ref(false);
const employeeContributionsRef = ref(null);
const formRef = ref(null);
const formValid = ref(false);

// Inline form
const showForm = ref(false);
const editingForTab = ref(null);
const editingRate = ref(null);
const form = ref(defaultForm());

// Calculator
const calcSalary = ref(10000);
const calcLoading = ref(false);
const calcResult = ref(null);

function defaultForm() {
  return {
    name: "",
    min_salary: null,
    max_salary: null,
    employee_rate: null,
    employee_fixed: null,
    employer_rate: null,
    employer_fixed: null,
    total_contribution: null,
    effective_date: new Date().toISOString().split("T")[0],
    end_date: null,
    is_active: true,
    notes: "",
  };
}

const rates = ref({ sss: [], philhealth: [], pagibig: [], tax: [] });

const rateTabs = [
  {
    value: "sss",
    label: "SSS",
    color: "blue",
    icon: "mdi-shield-account",
    description:
      "Social Security System contribution brackets based on Monthly Salary Credit (MSC).",
  },
  {
    value: "philhealth",
    label: "PhilHealth",
    color: "green",
    icon: "mdi-hospital-box",
    description:
      "Philippine Health Insurance Corporation premium rates (4.5% of monthly salary, shared 50/50).",
  },
  {
    value: "pagibig",
    label: "Pag-IBIG",
    color: "orange",
    icon: "mdi-home-city",
    description:
      "Home Development Mutual Fund (HDMF) contribution rates (2% employee, max ₱200/month).",
  },
  {
    value: "tax",
    label: "Tax",
    color: "purple",
    icon: "mdi-file-document",
    description:
      "Withholding tax brackets based on taxable income (TRAIN Law / CREATE Act).",
  },
];

const rules = {
  required: (v) => (v !== null && v !== undefined && v !== "") || "Required",
  requiredNumeric: (v) =>
    (v !== null && v !== undefined && v !== "") || "Required",
  rateRange: (v) => !v || (v >= 0 && v <= 100) || "Must be 0–100",
};

// ─── Computed ─────────────────────────────────────────────────────────────
const currentRates = (type) => rates.value[type] ?? [];

// ─── Lifecycle ────────────────────────────────────────────────────────────
onMounted(async () => {
  // Check access first for payrollist
  await checkGovRatesAccess();
  loadMyRequests();

  if (hasAccess.value) {
    loadRates();
  }
});

// ─── Methods ──────────────────────────────────────────────────────────────
const switchTab = (tab) => {
  cancelForm();
  activeTab.value = tab;
};

const loadRates = async () => {
  loading.value = true;
  try {
    const response = await api.get("/government-rates");
    const allRates = response.data.data || response.data || [];
    rates.value = {
      sss: allRates.filter((r) => r.type === "sss"),
      philhealth: allRates.filter((r) => r.type === "philhealth"),
      pagibig: allRates.filter((r) => r.type === "pagibig"),
      tax: allRates.filter((r) => r.type === "tax"),
    };
  } catch (error) {
    devLog.error("Failed to load rates:", error);
    toast.error("Failed to load government rates");
  } finally {
    loading.value = false;
  }
};

const startAdd = (tabType) => {
  editingRate.value = null;
  editingForTab.value = tabType;
  form.value = defaultForm();
  showForm.value = true;
};

const startEdit = (rate) => {
  editingRate.value = rate;
  editingForTab.value = rate.type;
  activeTab.value = rate.type;
  form.value = {
    ...rate,
    effective_date:
      rate.effective_date?.split("T")[0] ??
      new Date().toISOString().split("T")[0],
    end_date: rate.end_date?.split("T")[0] ?? null,
  };
  showForm.value = true;
};

const cancelForm = () => {
  showForm.value = false;
  editingForTab.value = null;
  editingRate.value = null;
  form.value = defaultForm();
  const ref = Array.isArray(formRef.value) ? formRef.value[0] : formRef.value;
  ref?.resetValidation();
};

const saveRate = async (tabType) => {
  const ref = Array.isArray(formRef.value) ? formRef.value[0] : formRef.value;
  const { valid } = await ref.validate();
  if (!valid) return;

  saving.value = true;
  try {
    const payload = { ...form.value, type: tabType };

    if (editingRate.value?.id) {
      await api.put(`/government-rates/${editingRate.value.id}`, payload);
      toast.success("Bracket updated");
    } else {
      await api.post("/government-rates", payload);
      toast.success("Bracket added");
    }

    cancelForm();
    await loadRates();
  } catch (error) {
    devLog.error("Failed to save rate:", error);
    const message = error.response?.data?.message || "Failed to save";
    const errors = error.response?.data?.errors;
    if (errors) {
      toast.error(`${message}: ${Object.values(errors).flat().join(", ")}`);
    } else {
      toast.error(message);
    }
  } finally {
    saving.value = false;
  }
};

const deleteRate = async (rate) => {
  if (!(await confirmDialog("Delete this bracket? This cannot be undone.")))
    return;
  try {
    await api.delete(`/government-rates/${rate.id}`);
    toast.success("Bracket deleted");
    if (editingRate.value?.id === rate.id) cancelForm();
    await loadRates();
  } catch {
    toast.error("Failed to delete bracket");
  }
};

const toggleActive = async (rate) => {
  try {
    await api.put(`/government-rates/${rate.id}`, {
      is_active: !rate.is_active,
    });
    toast.success(`Bracket ${rate.is_active ? "deactivated" : "activated"}`);
    await loadRates();
  } catch {
    toast.error("Failed to toggle status");
  }
};

const clearTabRates = async (tabType) => {
  const tabRates = currentRates(tabType);
  if (tabRates.length === 0) return;
  const label = rateTabs.find((t) => t.value === tabType)?.label || tabType;
  if (
    !(await confirmDialog(
      `Delete all ${tabRates.length} ${label} brackets? The system will use built-in fallback tables.`,
    ))
  )
    return;
  try {
    await api.post("/government-rates/bulk-delete", {
      ids: tabRates.map((r) => r.id),
    });
    toast.success(`Cleared all ${label} brackets`);
    cancelForm();
    await loadRates();
  } catch {
    toast.error("Failed to clear brackets");
  }
};

// ─── Calculator ───────────────────────────────────────────────────────────
const computeContributions = async () => {
  if (!calcSalary.value || calcSalary.value <= 0) {
    toast.warning("Please enter a valid salary");
    return;
  }
  calcLoading.value = true;
  try {
    const response = await api.post("/government/compute-contributions", {
      salary: calcSalary.value,
    });
    calcResult.value = response.data;
  } catch (error) {
    devLog.error("Calculator error:", error);
    toast.error("Failed to compute contributions");
  } finally {
    calcLoading.value = false;
  }
};
</script>

<style scoped>
.gov-rates-page {
  max-width: 1600px;
  margin: 0 auto;
  padding: 24px 24px 32px;
}

.back-button-wrapper {
  margin-bottom: 4px;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  background: rgba(255, 255, 255, 0.9);
  color: #001f3d;
  font-weight: 600;
  cursor: pointer;
  padding: 10px 14px;
  border-radius: 12px;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.back-button:hover {
  background: #ffffff;
  border-color: rgba(237, 152, 95, 0.35);
  color: #ed985f;
  transform: translateY(-1px);
}

.header-main {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  width: 100%;
  flex-wrap: wrap;
}

.rate-panel {
  padding: 0;
}

.rate-info-banner {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding: 16px 24px;
  font-size: 13px;
  font-weight: 500;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.rate-info-banner.sss {
  background: linear-gradient(
    135deg,
    rgba(33, 150, 243, 0.06) 0%,
    rgba(33, 150, 243, 0.02) 100%
  );
  color: #1565c0;
}

.rate-info-banner.philhealth {
  background: linear-gradient(
    135deg,
    rgba(76, 175, 80, 0.06) 0%,
    rgba(76, 175, 80, 0.02) 100%
  );
  color: #2e7d32;
}

.rate-info-banner.pagibig {
  background: linear-gradient(
    135deg,
    rgba(255, 152, 0, 0.06) 0%,
    rgba(255, 152, 0, 0.02) 100%
  );
  color: #e65100;
}

.rate-info-banner.tax {
  background: linear-gradient(
    135deg,
    rgba(156, 39, 176, 0.06) 0%,
    rgba(156, 39, 176, 0.02) 100%
  );
  color: #6a1b9a;
}

.rate-info-note {
  margin-left: auto;
  font-weight: 400;
  opacity: 0.7;
  font-size: 12px;
}

.rate-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 24px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
  background: #fafbfc;
  flex-wrap: wrap;
}

.form-panel {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.form-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px 0;
}

.form-panel-title {
  font-size: 14px;
  font-weight: 600;
  color: #334155;
  display: flex;
  align-items: center;
}

.rates-table-wrap {
  overflow-x: auto;
}

.rates-table :deep(thead tr th) {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #64748b;
  background: #f8fafc;
  white-space: nowrap;
  padding: 12px 16px;
  border-bottom: 2px solid rgba(0, 0, 0, 0.06);
}

.rates-table :deep(tbody tr td) {
  font-size: 13px;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.04);
  transition: background 0.15s;
}

.rates-table :deep(tbody tr.row-inactive td) {
  opacity: 0.45;
}

.rates-table :deep(tbody tr:hover td) {
  background: rgba(237, 152, 95, 0.04);
}

.mono {
  font-variant-numeric: tabular-nums;
}

.status-chip {
  cursor: pointer;
  transition: transform 0.15s;
}

.status-chip:hover {
  transform: scale(1.05);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 64px 24px;
  color: #94a3b8;
}

.empty-icon {
  opacity: 0.2;
}

.calculator-panel {
  padding: 28px;
}

.calc-header {
  display: flex;
  align-items: center;
  margin-bottom: 28px;
}

.calc-input-row {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.calc-input {
  max-width: 320px;
  flex: 1 1 280px;
}

.calc-results {
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.calc-salary-label {
  font-size: 14px;
  color: #64748b;
  margin-bottom: 16px;
}

.calc-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}

.calc-card {
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid rgba(0, 31, 61, 0.08);
  background: #fff;
  transition: all 0.3s ease;
}

.calc-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.calc-card-icon {
  padding: 10px 14px;
  display: flex;
  align-items: center;
}

.calc-sss .calc-card-icon {
  background: linear-gradient(135deg, #2196f3, #42a5f5);
}

.calc-ph .calc-card-icon {
  background: linear-gradient(135deg, #4caf50, #66bb6a);
}

.calc-pi .calc-card-icon {
  background: linear-gradient(135deg, #ff9800, #ffa726);
}

.calc-card-body {
  padding: 12px 14px;
}

.calc-card-title {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #64748b;
  margin-bottom: 8px;
}

.calc-card-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: #475569;
  padding: 3px 0;
}

.calc-card-row.highlight {
  color: #1e293b;
  font-weight: 600;
  padding-top: 6px;
}

.calc-total-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 18px;
  background: linear-gradient(135deg, #1e293b, #334155);
  border-radius: 12px;
  color: white;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.calc-total-label {
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
}

.calc-total-value {
  font-size: 20px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.calc-note {
  font-size: 12px;
  color: #94a3b8;
  display: flex;
  align-items: center;
}

@media (max-width: 960px) {
  .header-main {
    align-items: flex-start;
  }

  .rate-info-note {
    margin-left: 0;
    width: 100%;
  }

  .calc-cards {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 600px) {
  .gov-rates-page {
    padding: 16px 16px 24px;
  }

  .calc-total-bar {
    align-items: flex-start;
  }
}
</style>
