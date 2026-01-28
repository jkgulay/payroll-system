<template>
  <v-container fluid class="pa-6 report-container">
    <v-row>
      <v-col cols="12">
        <!-- Page Header -->
        <div class="page-header mb-6">
          <v-btn 
            icon 
            variant="text" 
            @click="$router.back()" 
            class="back-btn mr-4"
            size="large"
          >
            <v-icon>mdi-arrow-left</v-icon>
          </v-btn>
          <div class="d-flex align-center">
            <v-avatar color="orange-lighten-4" size="56" class="mr-4">
              <v-icon size="32" color="orange-darken-2">mdi-file-chart</v-icon>
            </v-avatar>
            <div>
              <h1 class="text-h4 font-weight-bold mb-1">Government Contributions Report</h1>
              <p class="text-body-1 text-medium-emphasis">
                Create, manage and process payroll for all employees
              </p>
            </div>
          </div>
        </div>

        <!-- Filters Card -->
        <v-card class="mb-6 filters-card" elevation="1" rounded="lg">
          <v-card-text class="pa-6">
            <v-row align="center" class="mb-2">
              <v-col cols="12">
                <div class="d-flex align-center mb-4">
                  <v-icon color="primary" class="mr-2">mdi-filter-variant</v-icon>
                  <span class="text-h6 font-weight-medium">Report Filters</span>
                </div>
              </v-col>
            </v-row>
            <v-row align="center">
              <v-col cols="12" sm="6" md="3">
                <v-select
                  v-model="selectedMonth"
                  :items="months"
                  item-title="label"
                  item-value="value"
                  label="Month"
                  prepend-inner-icon="mdi-calendar-month"
                  variant="outlined"
                  density="comfortable"
                  color="primary"
                  hide-details
                ></v-select>
              </v-col>
              <v-col cols="12" sm="6" md="3">
                <v-select
                  v-model="selectedYear"
                  :items="years"
                  label="Year"
                  prepend-inner-icon="mdi-calendar"
                  variant="outlined"
                  density="comfortable"
                  color="primary"
                  hide-details
                ></v-select>
              </v-col>
              <v-col cols="12" sm="6" md="3">
                <v-select
                  v-model="selectedDepartment"
                  :items="departments"
                  label="Department"
                  prepend-inner-icon="mdi-office-building"
                  variant="outlined"
                  density="comfortable"
                  color="primary"
                  hide-details
                  clearable
                ></v-select>
              </v-col>
              <v-col cols="12" sm="6" md="3">
                <v-btn
                  color="primary"
                  size="large"
                  prepend-icon="mdi-chart-box"
                  @click="loadReport"
                  :loading="loading"
                  block
                  elevation="2"
                  class="generate-btn"
                >
                  Generate Report
                </v-btn>
              </v-col>
            </v-row>
            
            <!-- Action Buttons Row -->
            <v-row class="mt-4">
              <v-col cols="12">
                <div class="d-flex flex-wrap gap-2">
                  <v-btn
                    color="success"
                    prepend-icon="mdi-microsoft-excel"
                    @click="exportReport"
                    :disabled="!reportData"
                    variant="elevated"
                  >
                    Export to Excel
                  </v-btn>
                  <v-btn
                    color="info"
                    prepend-icon="mdi-printer"
                    @click="printReport"
                    :disabled="!reportData"
                    variant="elevated"
                  >
                    Print Report
                  </v-btn>
                  <v-btn
                    color="secondary"
                    prepend-icon="mdi-email"
                    @click="showEmailDialog = true"
                    :disabled="!reportData"
                    variant="elevated"
                  >
                    Email Report
                  </v-btn>
                  <v-spacer></v-spacer>
                  <v-btn
                    :prepend-icon="showChart ? 'mdi-table' : 'mdi-chart-bar'"
                    @click="showChart = !showChart"
                    :disabled="!reportData"
                    variant="tonal"
                    color="primary"
                  >
                    {{ showChart ? 'Show Table' : 'Show Chart' }}
                  </v-btn>
                </div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <!-- Enhanced Summary Cards with Animation -->
        <v-slide-y-transition group>
          <v-row v-if="reportData" class="mb-6" key="summary-cards">
            <!-- Warning Alert for Zero Contributions -->
            <v-col cols="12" v-if="reportData.grand_total === 0 && reportData.employee_count > 0">
              <v-alert
                type="warning"
                variant="tonal"
                prominent
                border="start"
                class="mb-4 alert-animated"
                rounded="lg"
              >
                <v-alert-title class="text-h6 mb-2">
                  <v-icon class="mr-2">mdi-alert-circle</v-icon>
                  No Contributions Calculated
                </v-alert-title>
                <div class="mt-2">
                  Found {{ reportData.employee_count }} employees for {{ reportData.period }}, but no government contributions were calculated.
                  <br><br>
                  <strong>Possible reasons:</strong>
                  <ul class="mt-2 ml-4">
                    <li>No payroll has been processed for this period</li>
                    <li>Payroll exists but government contributions (SSS, PhilHealth, Pag-IBIG) were not calculated</li>
                    <li>Employee salaries may be below the minimum threshold for contributions</li>
                  </ul>
                  <br>
                  <strong>To fix:</strong> Generate or regenerate payroll for {{ reportData.period }} to calculate government contributions.
                </div>
              </v-alert>
            </v-col>

            <v-col cols="12" sm="6" lg="3">
              <v-card 
                class="summary-card sss-card h-100" 
                elevation="2" 
                rounded="lg"
                hover
              >
                <v-card-text class="pa-6">
                  <div class="d-flex align-center justify-space-between mb-3">
                    <div class="d-flex align-center">
                      <v-avatar color="blue-lighten-5" size="48" class="mr-3">
                        <v-icon color="blue-darken-2" size="28">mdi-shield-account</v-icon>
                      </v-avatar>
                      <span class="text-h6 font-weight-medium">SSS</span>
                    </div>
                    <v-chip size="small" color="blue" variant="tonal">Social Security</v-chip>
                  </div>
                  <div class="text-h3 font-weight-bold mb-3 text-primary">
                    ₱{{ formatNumber(reportData.sss_total) }}
                  </div>
                  <v-divider class="mb-3"></v-divider>
                  <div class="contribution-breakdown">
                    <div class="d-flex justify-space-between mb-2">
                      <span class="text-body-2 text-medium-emphasis">
                        <v-icon size="16" class="mr-1">mdi-account</v-icon>Employee Share
                      </span>
                      <span class="font-weight-medium">₱{{ formatNumber(reportData.sss_employee) }}</span>
                    </div>
                    <div class="d-flex justify-space-between">
                      <span class="text-body-2 text-medium-emphasis">
                        <v-icon size="16" class="mr-1">mdi-office-building</v-icon>Employer Share
                      </span>
                      <span class="font-weight-medium">₱{{ formatNumber(reportData.sss_employer) }}</span>
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12" sm="6" lg="3">
              <v-card 
                class="summary-card philhealth-card h-100" 
                elevation="2" 
                rounded="lg"
                hover
              >
                <v-card-text class="pa-6">
                  <div class="d-flex align-center justify-space-between mb-3">
                    <div class="d-flex align-center">
                      <v-avatar color="green-lighten-5" size="48" class="mr-3">
                        <v-icon color="green-darken-2" size="28">mdi-hospital-box</v-icon>
                      </v-avatar>
                      <span class="text-h6 font-weight-medium">PhilHealth</span>
                    </div>
                    <v-chip size="small" color="green" variant="tonal">Healthcare</v-chip>
                  </div>
                  <div class="text-h3 font-weight-bold mb-3 text-success">
                    ₱{{ formatNumber(reportData.philhealth_total) }}
                  </div>
                  <v-divider class="mb-3"></v-divider>
                  <div class="contribution-breakdown">
                    <div class="d-flex justify-space-between mb-2">
                      <span class="text-body-2 text-medium-emphasis">
                        <v-icon size="16" class="mr-1">mdi-account</v-icon>Employee Share
                      </span>
                      <span class="font-weight-medium">₱{{ formatNumber(reportData.philhealth_employee) }}</span>
                    </div>
                    <div class="d-flex justify-space-between">
                      <span class="text-body-2 text-medium-emphasis">
                        <v-icon size="16" class="mr-1">mdi-office-building</v-icon>Employer Share
                      </span>
                      <span class="font-weight-medium">₱{{ formatNumber(reportData.philhealth_employer) }}</span>
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12" sm="6" lg="3">
              <v-card 
                class="summary-card pagibig-card h-100" 
                elevation="2" 
                rounded="lg"
                hover
              >
                <v-card-text class="pa-6">
                  <div class="d-flex align-center justify-space-between mb-3">
                    <div class="d-flex align-center">
                      <v-avatar color="orange-lighten-5" size="48" class="mr-3">
                        <v-icon color="orange-darken-2" size="28">mdi-home-city</v-icon>
                      </v-avatar>
                      <span class="text-h6 font-weight-medium">Pag-IBIG</span>
                    </div>
                    <v-chip size="small" color="orange" variant="tonal">Housing</v-chip>
                  </div>
                  <div class="text-h3 font-weight-bold mb-3 text-orange">
                    ₱{{ formatNumber(reportData.pagibig_total) }}
                  </div>
                  <v-divider class="mb-3"></v-divider>
                  <div class="contribution-breakdown">
                    <div class="d-flex justify-space-between mb-2">
                      <span class="text-body-2 text-medium-emphasis">
                        <v-icon size="16" class="mr-1">mdi-account</v-icon>Employee Share
                      </span>
                      <span class="font-weight-medium">₱{{ formatNumber(reportData.pagibig_employee) }}</span>
                    </div>
                    <div class="d-flex justify-space-between">
                      <span class="text-body-2 text-medium-emphasis">
                        <v-icon size="16" class="mr-1">mdi-office-building</v-icon>Employer Share
                      </span>
                      <span class="font-weight-medium">₱{{ formatNumber(reportData.pagibig_employer) }}</span>
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12" sm="6" lg="3">
              <v-card 
                class="summary-card total-card h-100" 
                elevation="2" 
                rounded="lg"
                hover
              >
                <v-card-text class="pa-6">
                  <div class="d-flex align-center justify-space-between mb-3">
                    <div class="d-flex align-center">
                      <v-avatar color="purple-lighten-5" size="48" class="mr-3">
                        <v-icon color="purple-darken-2" size="28">mdi-cash-multiple</v-icon>
                      </v-avatar>
                      <span class="text-h6 font-weight-medium">Total</span>
                    </div>
                    <v-chip size="small" color="purple" variant="tonal">Overall</v-chip>
                  </div>
                  <div class="text-h3 font-weight-bold mb-3 text-purple">
                    ₱{{ formatNumber(reportData.grand_total) }}
                  </div>
                  <v-divider class="mb-3"></v-divider>
                  <div class="contribution-breakdown">
                    <div class="d-flex justify-space-between mb-2">
                      <span class="text-body-2 text-medium-emphasis">
                        <v-icon size="16" class="mr-1">mdi-account-multiple</v-icon>Total Employees
                      </span>
                      <span class="font-weight-bold text-h6">{{ reportData.employee_count }}</span>
                    </div>
                    <div class="d-flex justify-space-between">
                      <span class="text-body-2 text-medium-emphasis">
                        <v-icon size="16" class="mr-1">mdi-calendar-month</v-icon>Period
                      </span>
                      <span class="font-weight-medium">{{ months.find(m => m.value === selectedMonth)?.label }} {{ selectedYear }}</span>
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </v-slide-y-transition>

        <!-- Chart View -->
        <v-card v-if="reportData && showChart" class="mb-6" elevation="2" rounded="lg">
          <v-card-title class="pa-6 d-flex align-center">
            <v-icon class="mr-3" color="primary">mdi-chart-bar</v-icon>
            <span class="text-h5 font-weight-bold">Contributions Overview</span>
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-6">
            <v-row>
              <v-col cols="12" md="6">
                <div class="chart-container">
                  <canvas ref="contributionChart"></canvas>
                </div>
              </v-col>
              <v-col cols="12" md="6">
                <div class="chart-container">
                  <canvas ref="shareChart"></canvas>
                </div>
              </v-col>
            </v-row>
            <v-row class="mt-4">
              <v-col cols="12">
                <v-card variant="outlined" rounded="lg">
                  <v-card-text>
                    <div class="text-h6 mb-4 font-weight-medium">Quick Statistics</div>
                    <v-row>
                      <v-col cols="6" sm="3">
                        <div class="stat-box">
                          <div class="text-caption text-medium-emphasis mb-1">Average per Employee</div>
                          <div class="text-h6 font-weight-bold">₱{{ formatNumber(reportData.grand_total / reportData.employee_count) }}</div>
                        </div>
                      </v-col>
                      <v-col cols="6" sm="3">
                        <div class="stat-box">
                          <div class="text-caption text-medium-emphasis mb-1">Employee Share</div>
                          <div class="text-h6 font-weight-bold text-primary">₱{{ formatNumber(reportData.total_employee_contributions) }}</div>
                        </div>
                      </v-col>
                      <v-col cols="6" sm="3">
                        <div class="stat-box">
                          <div class="text-caption text-medium-emphasis mb-1">Employer Share</div>
                          <div class="text-h6 font-weight-bold text-success">₱{{ formatNumber(reportData.total_employer_contributions) }}</div>
                        </div>
                      </v-col>
                      <v-col cols="6" sm="3">
                        <div class="stat-box">
                          <div class="text-caption text-medium-emphasis mb-1">Total Remittance</div>
                          <div class="text-h6 font-weight-bold text-secondary">₱{{ formatNumber(reportData.grand_total) }}</div>
                        </div>
                      </v-col>
                    </v-row>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <!-- Enhanced Detailed Breakdown Table -->
        <v-card v-if="reportData && !showChart" elevation="2" rounded="lg">
          <v-card-title class="pa-6 d-flex align-center">
            <v-icon class="mr-3" color="primary">mdi-table-large</v-icon>
            <span class="text-h5 font-weight-bold">Detailed Employee Breakdown</span>
            <v-chip class="ml-3" color="primary" variant="tonal">{{ reportData.period }}</v-chip>
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-0">
            <v-data-table
              :headers="headers"
              :items="filteredEmployees"
              :items-per-page="itemsPerPage"
              :items-per-page-options="[
                { value: 25, title: '25' },
                { value: 50, title: '50' },
                { value: 100, title: '100' },
                { value: 200, title: '200' },
                { value: -1, title: 'All' }
              ]"
              class="elevation-0 enhanced-table"
              :loading="loading"
              density="comfortable"
            >
              <template v-slot:top>
                <v-toolbar flat class="px-6 py-4">
                  <div class="d-flex align-center">
                    <v-icon class="mr-2" color="primary">mdi-account-multiple</v-icon>
                    <span class="text-h6 font-weight-medium">{{ filteredEmployees.length }} Employee{{ filteredEmployees.length !== 1 ? 's' : '' }}</span>
                  </div>
                  <v-spacer></v-spacer>
                  <v-text-field
                    v-model="search"
                    prepend-inner-icon="mdi-magnify"
                    label="Search by name, number, or department"
                    single-line
                    hide-details
                    variant="outlined"
                    density="compact"
                    clearable
                    style="max-width: 400px;"
                  ></v-text-field>
                </v-toolbar>
              </template>

              <template v-slot:item.employee_number="{ item }">
                <div class="d-flex align-center">
                  <v-avatar color="primary" size="32" class="mr-2">
                    <span class="text-caption font-weight-bold">{{ item.employee_number.substring(0, 2) }}</span>
                  </v-avatar>
                  <span class="font-weight-medium">{{ item.employee_number }}</span>
                </div>
              </template>

              <template v-slot:item.full_name="{ item }">
                <div class="py-2">
                  <div class="font-weight-bold text-body-1">{{ item.full_name }}</div>
                  <div class="text-caption text-medium-emphasis">
                    <v-icon size="14" class="mr-1">mdi-briefcase</v-icon>{{ item.position }}
                  </div>
                </div>
              </template>

              <template v-slot:item.department="{ item }">
                <v-chip size="small" :color="getDepartmentColor(item.department)" variant="tonal">
                  <v-icon start size="14">mdi-office-building</v-icon>
                  {{ item.department }}
                </v-chip>
              </template>

              <template v-slot:item.sss_employee="{ item }">
                <span class="text-body-2">₱{{ formatNumber(item.sss_employee) }}</span>
              </template>

              <template v-slot:item.sss_employer="{ item }">
                <span class="text-body-2">₱{{ formatNumber(item.sss_employer) }}</span>
              </template>

              <template v-slot:item.sss_total="{ item }">
                <v-chip size="small" color="blue" variant="flat">
                  ₱{{ formatNumber(item.sss_total) }}
                </v-chip>
              </template>

              <template v-slot:item.philhealth_employee="{ item }">
                <span class="text-body-2">₱{{ formatNumber(item.philhealth_employee) }}</span>
              </template>

              <template v-slot:item.philhealth_employer="{ item }">
                <span class="text-body-2">₱{{ formatNumber(item.philhealth_employer) }}</span>
              </template>

              <template v-slot:item.philhealth_total="{ item }">
                <v-chip size="small" color="green" variant="flat">
                  ₱{{ formatNumber(item.philhealth_total) }}
                </v-chip>
              </template>

              <template v-slot:item.pagibig_employee="{ item }">
                <span class="text-body-2">₱{{ formatNumber(item.pagibig_employee) }}</span>
              </template>

              <template v-slot:item.pagibig_employer="{ item }">
                <span class="text-body-2">₱{{ formatNumber(item.pagibig_employer) }}</span>
              </template>

              <template v-slot:item.pagibig_total="{ item }">
                <v-chip size="small" color="orange" variant="flat">
                  ₱{{ formatNumber(item.pagibig_total) }}
                </v-chip>
              </template>

              <template v-slot:item.grand_total="{ item }">
                <v-chip size="small" color="purple" variant="flat" class="font-weight-bold">
                  ₱{{ formatNumber(item.grand_total) }}
                </v-chip>
              </template>

              <template v-slot:bottom>
                <v-divider></v-divider>
                <div class="pa-6 summary-footer">
                  <v-row align="center">
                    <v-col cols="12" md="6">
                      <div class="d-flex align-center">
                        <v-icon class="mr-2" color="primary">mdi-information</v-icon>
                        <span class="text-body-1 text-medium-emphasis">
                          Showing {{ filteredEmployees.length }} of {{ reportData.employees?.length || 0 }} employee(s) for {{ reportData.period }}
                        </span>
                      </div>
                    </v-col>
                    <v-col cols="12" md="6" class="text-right">
                      <div class="total-remittance-box">
                        <span class="text-h6 font-weight-medium mr-3">Total Remittance:</span>
                        <span class="text-h4 font-weight-bold text-purple">
                          ₱{{ formatNumber(calculateFilteredTotal()) }}
                        </span>
                      </div>
                    </v-col>
                  </v-row>
                </div>
              </template>

              <template v-slot:no-data>
                <div class="text-center pa-12">
                  <v-icon size="64" color="grey-lighten-2">mdi-file-document-alert-outline</v-icon>
                  <div class="text-h6 mt-4 mb-2 font-weight-medium">No Payroll Data Found</div>
                  <div class="text-body-1 text-medium-emphasis">
                    There are no payroll records for {{ reportData?.period || 'this period' }}.
                    <br>Generate a payroll for this period to see contribution data.
                  </div>
                </div>
              </template>
            </v-data-table>
          </v-card-text>
        </v-card>

        <!-- Empty State -->
        <v-card v-if="!reportData && !loading" elevation="2" rounded="lg">
          <v-card-text class="text-center pa-16">
            <v-icon size="96" color="primary" class="mb-4">mdi-chart-box-outline</v-icon>
            <h3 class="text-h4 font-weight-bold mb-3">No Report Generated Yet</h3>
            <p class="text-h6 text-medium-emphasis mb-6">
              Select a month and year from the filters above, then click "Generate Report" to view government contributions.
            </p>
            <v-btn
              color="primary"
              size="large"
              prepend-icon="mdi-chart-box"
              @click="loadReport"
              :loading="loading"
              elevation="2"
            >
              Generate Report Now
            </v-btn>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Email Dialog -->
    <v-dialog v-model="showEmailDialog" max-width="600px">
      <v-card rounded="lg">
        <v-card-title class="pa-6 d-flex align-center bg-primary">
          <v-icon class="mr-3" color="white">mdi-email</v-icon>
          <span class="text-h6 font-weight-bold text-white">Email Report</span>
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="pa-6">
          <v-text-field
            v-model="emailAddress"
            label="Email Address"
            prepend-inner-icon="mdi-email"
            variant="outlined"
            type="email"
            hint="Enter the recipient's email address"
            persistent-hint
            class="mb-4"
          ></v-text-field>
          <v-textarea
            v-model="emailMessage"
            label="Message (Optional)"
            prepend-inner-icon="mdi-message-text"
            variant="outlined"
            rows="3"
            hint="Add a custom message to include with the report"
            persistent-hint
          ></v-textarea>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showEmailDialog = false">Cancel</v-btn>
          <v-btn color="primary" variant="elevated" @click="sendEmail" :loading="sendingEmail">
            <v-icon start>mdi-send</v-icon>
            Send Report
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Success Snackbar -->
    <v-snackbar 
      v-model="snackbar" 
      :color="snackbarColor" 
      :timeout="4000"
      location="top right"
      elevation="6"
    >
      <div class="d-flex align-center">
        <v-icon class="mr-2">
          {{ snackbarColor === 'success' ? 'mdi-check-circle' : snackbarColor === 'error' ? 'mdi-alert-circle' : 'mdi-information' }}
        </v-icon>
        <span>{{ snackbarText }}</span>
      </div>
      <template v-slot:actions>
        <v-btn variant="text" @click="snackbar = false" icon size="small">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </template>
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import api from '@/services/api'
import * as XLSX from 'xlsx'

const loading = ref(false)
const reportData = ref(null)
const selectedMonth = ref(new Date().getMonth() + 1)
const selectedYear = ref(new Date().getFullYear())
const selectedDepartment = ref(null)
const search = ref('')
const showChart = ref(false)
const itemsPerPage = ref(50)

const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')

const showEmailDialog = ref(false)
const emailAddress = ref('')
const emailMessage = ref('')
const sendingEmail = ref(false)

const contributionChart = ref(null)
const shareChart = ref(null)

const months = [
  { label: 'January', value: 1 },
  { label: 'February', value: 2 },
  { label: 'March', value: 3 },
  { label: 'April', value: 4 },
  { label: 'May', value: 5 },
  { label: 'June', value: 6 },
  { label: 'July', value: 7 },
  { label: 'August', value: 8 },
  { label: 'September', value: 9 },
  { label: 'October', value: 10 },
  { label: 'November', value: 11 },
  { label: 'December', value: 12 }
]

const years = []
const currentYear = new Date().getFullYear()
for (let i = currentYear; i >= currentYear - 5; i--) {
  years.push(i)
}

const departments = computed(() => {
  if (!reportData.value?.employees) return ['All Departments']
  const depts = [...new Set(reportData.value.employees.map(e => e.department))]
  return ['All Departments', ...depts.sort()]
})

const filteredEmployees = computed(() => {
  if (!reportData.value?.employees) return []
  
  let filtered = reportData.value.employees

  // Filter by department
  if (selectedDepartment.value && selectedDepartment.value !== 'All Departments') {
    filtered = filtered.filter(emp => emp.department === selectedDepartment.value)
  }

  // Filter by search
  if (search.value) {
    const searchLower = search.value.toLowerCase()
    filtered = filtered.filter(emp =>
      emp.full_name.toLowerCase().includes(searchLower) ||
      emp.employee_number.toLowerCase().includes(searchLower) ||
      emp.department.toLowerCase().includes(searchLower) ||
      emp.position.toLowerCase().includes(searchLower)
    )
  }

  return filtered
})

const headers = [
  { title: 'Employee #', key: 'employee_number', sortable: true },
  { title: 'Employee Name', key: 'full_name', sortable: true },
  { title: 'Department', key: 'department', sortable: true },
  { title: 'SSS (EE)', key: 'sss_employee', align: 'end' },
  { title: 'SSS (ER)', key: 'sss_employer', align: 'end' },
  { title: 'SSS Total', key: 'sss_total', align: 'end' },
  { title: 'PhilHealth (EE)', key: 'philhealth_employee', align: 'end' },
  { title: 'PhilHealth (ER)', key: 'philhealth_employer', align: 'end' },
  { title: 'PhilHealth Total', key: 'philhealth_total', align: 'end' },
  { title: 'Pag-IBIG (EE)', key: 'pagibig_employee', align: 'end' },
  { title: 'Pag-IBIG (ER)', key: 'pagibig_employer', align: 'end' },
  { title: 'Pag-IBIG Total', key: 'pagibig_total', align: 'end' },
  { title: 'Grand Total', key: 'grand_total', align: 'end' }
]

const formatNumber = (value) => {
  if (value === null || value === undefined || value === '') return '0.00'
  const num = typeof value === 'string' ? parseFloat(value) : value
  if (isNaN(num)) return '0.00'
  return num.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

const getDepartmentColor = (department) => {
  const colors = ['primary', 'success', 'warning', 'info', 'error', 'secondary']
  const hash = department.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)
  return colors[hash % colors.length]
}

const calculateFilteredTotal = () => {
  if (!filteredEmployees.value.length) return 0
  return filteredEmployees.value.reduce((sum, emp) => sum + parseFloat(emp.grand_total || 0), 0)
}

const loadReport = async () => {
  try {
    loading.value = true
    const response = await api.get('/reports/government-remittance', {
      params: {
        month: selectedMonth.value,
        year: selectedYear.value
      }
    })
    reportData.value = response.data
    
    // Log the response for debugging
    console.log('Government Contributions Report Data:', response.data)
    
    // Check if there's actual contribution data
    if (response.data.employee_count === 0) {
      showSnackbar('No employees found for this period', 'warning')
    } else if (response.data.grand_total === 0) {
      showSnackbar(`Report generated for ${response.data.employee_count} employees, but no contributions found. Ensure payroll has been processed with government deductions.`, 'warning')
    } else {
      showSnackbar('Report generated successfully', 'success')
    }
  } catch (error) {
    console.error('Error loading report:', error)
    showSnackbar(error.response?.data?.message || 'Failed to load report', 'error')
  } finally {
    loading.value = false
  }
}

const printReport = () => {
  window.print()
}

const sendEmail = async () => {
  if (!emailAddress.value) {
    showSnackbar('Please enter an email address', 'error')
    return
  }

  sendingEmail.value = true
  try {
    // Simulate email sending - replace with actual API call
    await new Promise(resolve => setTimeout(resolve, 2000))
    showSnackbar(`Report sent successfully to ${emailAddress.value}`, 'success')
    showEmailDialog.value = false
    emailAddress.value = ''
    emailMessage.value = ''
  } catch (error) {
    showSnackbar('Failed to send email', 'error')
  } finally {
    sendingEmail.value = false
  }
}

const createCharts = async () => {
  await nextTick()
  
  if (!reportData.value || !showChart.value) return

  // Dynamically import Chart.js only when needed
  const { Chart, registerables } = await import('chart.js')
  Chart.register(...registerables)

  // Contribution Chart
  if (contributionChart.value) {
    const ctx = contributionChart.value.getContext('2d')
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['SSS', 'PhilHealth', 'Pag-IBIG'],
        datasets: [
          {
            label: 'Employee Share',
            data: [
              reportData.value.sss_employee,
              reportData.value.philhealth_employee,
              reportData.value.pagibig_employee
            ],
            backgroundColor: 'rgba(33, 150, 243, 0.8)',
            borderColor: 'rgba(33, 150, 243, 1)',
            borderWidth: 2
          },
          {
            label: 'Employer Share',
            data: [
              reportData.value.sss_employer,
              reportData.value.philhealth_employer,
              reportData.value.pagibig_employer
            ],
            backgroundColor: 'rgba(76, 175, 80, 0.8)',
            borderColor: 'rgba(76, 175, 80, 1)',
            borderWidth: 2
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          title: {
            display: true,
            text: 'Contributions by Type',
            font: { size: 16, weight: 'bold' }
          },
          legend: {
            position: 'bottom'
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: value => '₱' + value.toLocaleString()
            }
          }
        }
      }
    })
  }

  // Share Chart
  if (shareChart.value) {
    const ctx = shareChart.value.getContext('2d')
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['SSS', 'PhilHealth', 'Pag-IBIG'],
        datasets: [{
          data: [
            reportData.value.sss_total,
            reportData.value.philhealth_total,
            reportData.value.pagibig_total
          ],
          backgroundColor: [
            'rgba(33, 150, 243, 0.8)',
            'rgba(76, 175, 80, 0.8)',
            'rgba(255, 152, 0, 0.8)'
          ],
          borderColor: [
            'rgba(33, 150, 243, 1)',
            'rgba(76, 175, 80, 1)',
            'rgba(255, 152, 0, 1)'
          ],
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          title: {
            display: true,
            text: 'Total Contributions Distribution',
            font: { size: 16, weight: 'bold' }
          },
          legend: {
            position: 'bottom'
          },
          tooltip: {
            callbacks: {
              label: context => {
                const label = context.label || ''
                const value = context.parsed || 0
                const total = context.dataset.data.reduce((a, b) => a + b, 0)
                const percentage = ((value / total) * 100).toFixed(1)
                return `${label}: ₱${value.toLocaleString()} (${percentage}%)`
              }
            }
          }
        }
      }
    })
  }
}

watch(showChart, () => {
  if (showChart.value) {
    createCharts()
  }
})

const exportReport = () => {
  if (!reportData.value) return

  try {
    // Create workbook
    const wb = XLSX.utils.book_new()

    // Summary sheet
    const summaryData = [
      ['Government Contributions Report'],
      ['Period:', reportData.value.period],
      ['Total Employees:', reportData.value.employee_count],
      [],
      ['Contribution Type', 'Employee Share', 'Employer Share', 'Total Amount'],
      ['SSS', reportData.value.sss_employee, reportData.value.sss_employer, reportData.value.sss_total],
      ['PhilHealth', reportData.value.philhealth_employee, reportData.value.philhealth_employer, reportData.value.philhealth_total],
      ['Pag-IBIG', reportData.value.pagibig_employee, reportData.value.pagibig_employer, reportData.value.pagibig_total],
      [],
      ['Total Employee Contributions:', reportData.value.total_employee_contributions],
      ['Total Employer Contributions:', reportData.value.total_employer_contributions],
      ['Grand Total Remittance:', reportData.value.grand_total]
    ]
    const wsSummary = XLSX.utils.aoa_to_sheet(summaryData)
    XLSX.utils.book_append_sheet(wb, wsSummary, 'Summary')

    // Detailed breakdown sheet
    const detailedData = [
      ['Employee #', 'Employee Name', 'Department', 'Position', 
       'SSS (EE)', 'SSS (ER)', 'SSS Total',
       'PhilHealth (EE)', 'PhilHealth (ER)', 'PhilHealth Total',
       'Pag-IBIG (EE)', 'Pag-IBIG (ER)', 'Pag-IBIG Total',
       'Grand Total']
    ]

    reportData.value.employees.forEach(emp => {
      detailedData.push([
        emp.employee_number,
        emp.full_name,
        emp.department,
        emp.position,
        emp.sss_employee,
        emp.sss_employer,
        emp.sss_total,
        emp.philhealth_employee,
        emp.philhealth_employer,
        emp.philhealth_total,
        emp.pagibig_employee,
        emp.pagibig_employer,
        emp.pagibig_total,
        emp.grand_total
      ])
    })

    const wsDetailed = XLSX.utils.aoa_to_sheet(detailedData)
    XLSX.utils.book_append_sheet(wb, wsDetailed, 'Detailed Breakdown')

    // Generate file
    const fileName = `Government_Contributions_${reportData.value.period.replace(' ', '_')}.xlsx`
    XLSX.writeFile(wb, fileName)

    showSnackbar('Report exported successfully', 'success')
  } catch (error) {
    console.error('Error exporting report:', error)
    showSnackbar('Failed to export report', 'error')
  }
}

const showSnackbar = (text, color = 'success') => {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

onMounted(() => {
  // Auto-load report for current month
  loadReport()
})
</script>

<style scoped lang="scss">
.report-container {
  background: #f5f5f5;
  min-height: 100vh;
}

.page-header {
  display: flex;
  align-items: center;
  padding: 0;
  background: transparent;

  .back-btn {
    transition: transform 0.2s;
    &:hover {
      transform: translateX(-4px);
    }
  }
}

.filters-card {
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.08);
}

.generate-btn {
  font-weight: 600;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  transition: all 0.3s ease;
  
  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
  }
}

.summary-card {
  position: relative;
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border: 1px solid rgba(0, 0, 0, 0.08);
  background: white;

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: currentColor;
    opacity: 0.7;
  }

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
  }

  .text-h3 {
    color: rgba(0, 0, 0, 0.87) !important;
    font-size: 2.5rem !important;
    line-height: 1.2 !important;
  }

  .text-primary {
    color: #2196F3 !important;
  }

  .text-success {
    color: #4CAF50 !important;
  }

  .text-orange {
    color: #FF9800 !important;
  }

  .text-purple {
    color: #9C27B0 !important;
  }

  .contribution-breakdown {
    background: rgba(0, 0, 0, 0.02);
    padding: 12px;
    border-radius: 8px;
    
    span {
      color: rgba(0, 0, 0, 0.87);
    }
  }
}

.sss-card::before {
  background: linear-gradient(180deg, #2196F3 0%, #1976D2 100%);
}

.philhealth-card::before {
  background: linear-gradient(180deg, #4CAF50 0%, #388E3C 100%);
}

.pagibig-card::before {
  background: linear-gradient(180deg, #FF9800 0%, #F57C00 100%);
}

.total-card::before {
  background: linear-gradient(180deg, #9C27B0 0%, #7B1FA2 100%);
}

.alert-animated {
  animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.chart-container {
  position: relative;
  height: 350px;
  padding: 16px;
}

.stat-box {
  padding: 16px;
  text-align: center;
  border-radius: 12px;
  background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
  border: 1px solid rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
  }
}

.enhanced-table {
  :deep(.v-data-table-header) {
    background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
    
    th {
      font-weight: 700 !important;
      font-size: 0.875rem !important;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: rgba(0, 0, 0, 0.87) !important;
      border-bottom: 2px solid rgba(0, 0, 0, 0.12) !important;
    }
  }

  :deep(tbody tr) {
    transition: all 0.2s ease;

    &:hover {
      background: rgba(33, 150, 243, 0.05);
      transform: scale(1.01);
    }
  }

  :deep(.v-data-table__td) {
    padding: 16px 12px !important;
  }
}

.summary-footer {
  background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
  border-top: 2px solid rgba(0, 0, 0, 0.08);

  .total-remittance-box {
    padding: 16px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    color: white;
    display: inline-flex;
    align-items: center;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
  }
}

// Print styles
@media print {
  .report-container {
    background: white !important;
  }

  .filters-card,
  .back-btn,
  .v-btn,
  .page-header .v-icon {
    display: none !important;
  }

  .summary-card,
  .enhanced-table {
    page-break-inside: avoid;
  }

  .page-header {
    box-shadow: none !important;
    border-bottom: 2px solid #000;
  }
}

// Responsive adjustments
@media (max-width: 960px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .summary-card {
    margin-bottom: 16px;
  }

  .chart-container {
    height: 250px;
  }
}

</style>