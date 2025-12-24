# Philippine Payroll Computation Logic

## Overview

This document details the step-by-step payroll computation for Philippine payroll compliance, including SSS, PhilHealth, Pag-IBIG, and withholding tax calculations.

---

## Computation Flow

```
1. Calculate Basic Pay
   ↓
2. Add Overtime Pay
   ↓
3. Add Holiday Pay
   ↓
4. Add Night Differential
   ↓
5. Add Allowances
   ↓
6. Add Bonuses/Incentives
   ↓
7. GROSS PAY
   ↓
8. Compute SSS Contribution
   ↓
9. Compute PhilHealth Contribution
   ↓
10. Compute Pag-IBIG Contribution
   ↓
11. Calculate Taxable Income
   ↓
12. Compute Withholding Tax
   ↓
13. Add Other Deductions
   ↓
14. Add Loan Deductions
   ↓
15. TOTAL DEDUCTIONS
   ↓
16. NET PAY = Gross Pay - Total Deductions
```

---

## 1. Basic Pay Calculation

### For Daily-Paid Employees (Semi-Monthly)

```javascript
/**
 * Calculate basic pay for daily-paid employees
 *
 * @param {number} dailyRate - Employee's daily rate
 * @param {number} daysWorked - Actual days worked in the period
 * @returns {number} Basic pay
 */
function calculateBasicPay(dailyRate, daysWorked) {
  return dailyRate * daysWorked;
}

// Example:
// Daily rate: ₱600
// Days worked: 12 days
// Basic Pay = 600 × 12 = ₱7,200
```

### Days Worked Calculation

```javascript
/**
 * Calculate days worked from attendance records
 *
 * @param {Array} attendance - Array of attendance records
 * @returns {number} Total days worked
 */
function calculateDaysWorked(attendance) {
  let daysWorked = 0;

  for (const record of attendance) {
    if (record.status === "present") {
      // Convert hours to days (8 hours = 1 day)
      const hoursWorked = record.regular_hours + record.overtime_hours;
      daysWorked += hoursWorked / 8;
    }
  }

  return daysWorked;
}
```

---

## 2. Overtime Pay Calculation

### Regular Overtime (Beyond 8 hours)

```javascript
/**
 * Calculate overtime pay
 *
 * @param {number} hourlyRate - Employee's hourly rate (daily rate / 8)
 * @param {number} overtimeHours - Total overtime hours
 * @param {number} multiplier - Overtime multiplier (default: 1.25)
 * @returns {number} Overtime pay
 */
function calculateOvertimePay(hourlyRate, overtimeHours, multiplier = 1.25) {
  return hourlyRate * overtimeHours * multiplier;
}

// Example:
// Daily rate: ₱600
// Hourly rate: 600 / 8 = ₱75
// Overtime hours: 10 hours
// Overtime Pay = 75 × 10 × 1.25 = ₱937.50
```

### Philippine Overtime Rates

| Type                        | Multiplier  |
| --------------------------- | ----------- |
| Regular Day Overtime        | 1.25 (125%) |
| Rest Day                    | 1.30 (130%) |
| Rest Day + Overtime         | 1.69 (169%) |
| Special Non-Working Holiday | 1.30 (130%) |
| Special Holiday + Overtime  | 1.69 (169%) |
| Regular Holiday             | 2.00 (200%) |
| Regular Holiday + Overtime  | 2.60 (260%) |

---

## 3. Holiday Pay Calculation

### Regular Holiday (Work or No Work)

```javascript
/**
 * Calculate holiday pay
 *
 * @param {number} dailyRate - Employee's daily rate
 * @param {string} holidayType - 'regular' or 'special_non_working'
 * @param {boolean} worked - Did employee work on holiday?
 * @param {number} hoursWorked - Hours worked if applicable
 * @returns {number} Holiday pay
 */
function calculateHolidayPay(
  dailyRate,
  holidayType,
  worked = false,
  hoursWorked = 0
) {
  if (holidayType === "regular") {
    if (!worked) {
      // Regular holiday, did not work: 100% pay
      return dailyRate;
    } else {
      // Regular holiday, worked: 200% + 30% overtime per hour
      const baseHolidayPay = dailyRate * 2;
      const overtimeHours = Math.max(0, hoursWorked - 8);
      const overtimePay = (dailyRate / 8) * overtimeHours * 0.3;
      return baseHolidayPay + overtimePay;
    }
  } else if (holidayType === "special_non_working") {
    if (!worked) {
      // Special holiday, did not work: No pay (unless company policy says otherwise)
      return 0;
    } else {
      // Special holiday, worked: 130% + 30% overtime per hour
      const baseHolidayPay = dailyRate * 1.3;
      const overtimeHours = Math.max(0, hoursWorked - 8);
      const overtimePay = (dailyRate / 8) * overtimeHours * 0.3;
      return baseHolidayPay + overtimePay;
    }
  }

  return 0;
}

// Example:
// Daily rate: ₱600
// Regular Holiday, worked 8 hours
// Holiday Pay = 600 × 2 = ₱1,200
```

---

## 4. Night Differential

### Night Shift Premium (10:00 PM - 6:00 AM)

```javascript
/**
 * Calculate night differential pay
 *
 * @param {number} hourlyRate - Employee's hourly rate
 * @param {number} nightHours - Hours worked during night shift
 * @param {number} rate - Night differential rate (default: 0.10 or 10%)
 * @returns {number} Night differential pay
 */
function calculateNightDifferential(hourlyRate, nightHours, rate = 0.1) {
  return hourlyRate * nightHours * rate;
}

// Example:
// Hourly rate: ₱75
// Night hours: 5 hours
// Night Differential = 75 × 5 × 0.10 = ₱37.50
```

---

## 5. Allowances

### Taxable vs Non-Taxable Allowances

```javascript
/**
 * Calculate total allowances
 *
 * @param {Array} allowances - Array of allowance objects
 * @returns {Object} Taxable and non-taxable allowances
 */
function calculateAllowances(allowances) {
  let taxable = 0;
  let nonTaxable = 0;

  for (const allowance of allowances) {
    if (allowance.is_taxable) {
      taxable += allowance.amount;
    } else {
      nonTaxable += allowance.amount;
    }
  }

  return { taxable, nonTaxable, total: taxable + nonTaxable };
}
```

### Common Allowances

| Allowance Type         | Typical Tax Treatment               |
| ---------------------- | ----------------------------------- |
| Rice Subsidy           | Non-taxable (up to ₱2,000/month)    |
| Clothing Allowance     | Non-taxable (up to ₱6,000/year)     |
| Laundry Allowance      | Non-taxable (up to ₱300/month)      |
| Medical Cash Allowance | Taxable (unless under benefit plan) |
| Transportation         | Taxable                             |
| Communication          | Taxable                             |
| Housing                | Taxable                             |

---

## 6. Gross Pay Calculation

```javascript
/**
 * Calculate gross pay
 *
 * @param {Object} components - Pay components
 * @returns {number} Gross pay
 */
function calculateGrossPay(components) {
  const {
    basicPay,
    overtimePay,
    holidayPay,
    nightDifferential,
    allowances,
    bonuses,
  } = components;

  return (
    basicPay +
    overtimePay +
    holidayPay +
    nightDifferential +
    allowances +
    bonuses
  );
}
```

---

## 7. SSS Contribution Calculation

### 2025 SSS Contribution Table (Sample - Update as needed)

```javascript
const sssTable = [
  { minRange: 0, maxRange: 4249.99, employeeShare: 180.0 },
  { minRange: 4250, maxRange: 4749.99, employeeShare: 202.5 },
  { minRange: 4750, maxRange: 5249.99, employeeShare: 225.0 },
  { minRange: 5250, maxRange: 5749.99, employeeShare: 247.5 },
  { minRange: 5750, maxRange: 6249.99, employeeShare: 270.0 },
  { minRange: 6250, maxRange: 6749.99, employeeShare: 292.5 },
  { minRange: 6750, maxRange: 7249.99, employeeShare: 315.0 },
  { minRange: 7250, maxRange: 7749.99, employeeShare: 337.5 },
  { minRange: 7750, maxRange: 8249.99, employeeShare: 360.0 },
  { minRange: 8250, maxRange: 8749.99, employeeShare: 382.5 },
  { minRange: 8750, maxRange: 9249.99, employeeShare: 405.0 },
  { minRange: 9250, maxRange: 9749.99, employeeShare: 427.5 },
  { minRange: 9750, maxRange: 10249.99, employeeShare: 450.0 },
  { minRange: 10250, maxRange: 10749.99, employeeShare: 472.5 },
  { minRange: 10750, maxRange: 11249.99, employeeShare: 495.0 },
  { minRange: 11250, maxRange: 11749.99, employeeShare: 517.5 },
  { minRange: 11750, maxRange: 12249.99, employeeShare: 540.0 },
  { minRange: 12250, maxRange: 12749.99, employeeShare: 562.5 },
  { minRange: 12750, maxRange: 13249.99, employeeShare: 585.0 },
  { minRange: 13250, maxRange: 13749.99, employeeShare: 607.5 },
  { minRange: 13750, maxRange: 14249.99, employeeShare: 630.0 },
  { minRange: 14250, maxRange: 14749.99, employeeShare: 652.5 },
  { minRange: 14750, maxRange: 15249.99, employeeShare: 675.0 },
  { minRange: 15250, maxRange: 15749.99, employeeShare: 697.5 },
  { minRange: 15750, maxRange: 16249.99, employeeShare: 720.0 },
  { minRange: 16250, maxRange: 16749.99, employeeShare: 742.5 },
  { minRange: 16750, maxRange: 17249.99, employeeShare: 765.0 },
  { minRange: 17250, maxRange: 17749.99, employeeShare: 787.5 },
  { minRange: 17750, maxRange: 18249.99, employeeShare: 810.0 },
  { minRange: 18250, maxRange: 18749.99, employeeShare: 832.5 },
  { minRange: 18750, maxRange: 19249.99, employeeShare: 855.0 },
  { minRange: 19250, maxRange: 19749.99, employeeShare: 877.5 },
  { minRange: 19750, maxRange: 99999.99, employeeShare: 900.0 },
];

/**
 * Calculate SSS contribution (employee share)
 * Note: For semi-monthly, divide monthly compensation by 2
 *
 * @param {number} monthlyCompensation - Monthly salary
 * @returns {number} SSS contribution
 */
function calculateSSS(monthlyCompensation) {
  const bracket = sssTable.find(
    (row) =>
      monthlyCompensation >= row.minRange && monthlyCompensation <= row.maxRange
  );

  return bracket ? bracket.employeeShare : 900.0; // Max contribution
}

// Example:
// Monthly compensation: ₱15,000
// For semi-monthly: Look up ₱15,000 in table
// SSS = ₱675.00 per semi-monthly period
```

---

## 8. PhilHealth Contribution Calculation

### 2025 PhilHealth Rate: 4% (2% employee, 2% employer)

```javascript
/**
 * Calculate PhilHealth contribution (employee share)
 *
 * @param {number} monthlyBasicSalary - Monthly basic salary
 * @returns {number} PhilHealth contribution
 */
function calculatePhilHealth(monthlyBasicSalary) {
  const PHIL_HEALTH_RATE = 0.04; // 4% total (2% employee + 2% employer)
  const MIN_SALARY = 10000;
  const MAX_SALARY = 80000;

  // Use actual salary, but cap between min and max
  let contributionBase = monthlyBasicSalary;

  if (contributionBase < MIN_SALARY) {
    contributionBase = MIN_SALARY;
  } else if (contributionBase > MAX_SALARY) {
    contributionBase = MAX_SALARY;
  }

  const totalContribution = contributionBase * PHIL_HEALTH_RATE;
  const employeeShare = totalContribution / 2;

  return employeeShare;
}

// Example:
// Monthly salary: ₱15,000
// Total contribution: 15,000 × 0.04 = ₱600
// Employee share: 600 / 2 = ₱300
// For semi-monthly: 300 / 2 = ₱150
```

---

## 9. Pag-IBIG Contribution Calculation

### 2025 Pag-IBIG Rates

```javascript
/**
 * Calculate Pag-IBIG contribution
 *
 * @param {number} monthlyCompensation - Monthly compensation
 * @returns {number} Pag-IBIG contribution
 */
function calculatePagIBIG(monthlyCompensation) {
  let employeeRate;

  if (monthlyCompensation <= 1500) {
    employeeRate = 0.01; // 1%
  } else {
    employeeRate = 0.02; // 2%
  }

  let contribution = monthlyCompensation * employeeRate;

  // Cap at ₱200 maximum per month
  const MAX_CONTRIBUTION = 200;
  if (contribution > MAX_CONTRIBUTION) {
    contribution = MAX_CONTRIBUTION;
  }

  return contribution;
}

// Example:
// Monthly compensation: ₱15,000
// Rate: 2%
// Contribution: 15,000 × 0.02 = ₱300 (capped at ₱200)
// Pag-IBIG = ₱200 per month
// For semi-monthly: 200 / 2 = ₱100
```

---

## 10. Taxable Income Calculation

```javascript
/**
 * Calculate taxable income
 *
 * @param {Object} components - Income and deduction components
 * @returns {number} Taxable income
 */
function calculateTaxableIncome(components) {
  const {
    grossPay,
    sss,
    philHealth,
    pagIbig,
    nonTaxableAllowances,
    nonTaxableBonuses,
  } = components;

  // Taxable income = Gross Pay - Government Contributions - Non-taxable items
  const taxableIncome =
    grossPay -
    sss -
    philHealth -
    pagIbig -
    nonTaxableAllowances -
    nonTaxableBonuses;

  return Math.max(0, taxableIncome);
}
```

---

## 11. Withholding Tax Calculation (TRAIN Law)

### 2025 Tax Table (Annual Basis)

| Annual Taxable Income   | Tax Rate                                   |
| ----------------------- | ------------------------------------------ |
| ₱0 - ₱250,000           | 0%                                         |
| ₱250,000 - ₱400,000     | 15% of excess over ₱250,000                |
| ₱400,000 - ₱800,000     | ₱22,500 + 20% of excess over ₱400,000      |
| ₱800,000 - ₱2,000,000   | ₱102,500 + 25% of excess over ₱800,000     |
| ₱2,000,000 - ₱8,000,000 | ₱402,500 + 30% of excess over ₱2,000,000   |
| Over ₱8,000,000         | ₱2,202,500 + 35% of excess over ₱8,000,000 |

### Semi-Monthly Tax Table

| Semi-Monthly Taxable Income | Tax                                      |
| --------------------------- | ---------------------------------------- |
| ₱0 - ₱10,416                | 0%                                       |
| ₱10,417 - ₱16,666           | 0% + 15% of excess over ₱10,416          |
| ₱16,667 - ₱33,332           | ₱937.50 + 20% of excess over ₱16,666     |
| ₱33,333 - ₱83,332           | ₱4,270.83 + 25% of excess over ₱33,332   |
| ₱83,333 - ₱333,332          | ₱16,770.83 + 30% of excess over ₱83,332  |
| Over ₱333,333               | ₱91,770.83 + 35% of excess over ₱333,332 |

```javascript
const taxTableSemiMonthly = [
  { minRange: 0, maxRange: 10416, baseTax: 0, excessRate: 0, excessOver: 0 },
  {
    minRange: 10417,
    maxRange: 16666,
    baseTax: 0,
    excessRate: 0.15,
    excessOver: 10416,
  },
  {
    minRange: 16667,
    maxRange: 33332,
    baseTax: 937.5,
    excessRate: 0.2,
    excessOver: 16666,
  },
  {
    minRange: 33333,
    maxRange: 83332,
    baseTax: 4270.83,
    excessRate: 0.25,
    excessOver: 33332,
  },
  {
    minRange: 83333,
    maxRange: 333332,
    baseTax: 16770.83,
    excessRate: 0.3,
    excessOver: 83332,
  },
  {
    minRange: 333333,
    maxRange: Infinity,
    baseTax: 91770.83,
    excessRate: 0.35,
    excessOver: 333332,
  },
];

/**
 * Calculate withholding tax (semi-monthly)
 *
 * @param {number} taxableIncome - Semi-monthly taxable income
 * @returns {number} Withholding tax
 */
function calculateWithholdingTax(taxableIncome) {
  const bracket = taxTableSemiMonthly.find(
    (row) => taxableIncome >= row.minRange && taxableIncome <= row.maxRange
  );

  if (!bracket) return 0;

  const excess = taxableIncome - bracket.excessOver;
  const tax = bracket.baseTax + excess * bracket.excessRate;

  return Math.max(0, tax);
}

// Example:
// Semi-monthly taxable income: ₱20,000
// Bracket: ₱16,667 - ₱33,332
// Base tax: ₱937.50
// Excess: 20,000 - 16,666 = ₱3,334
// Tax on excess: 3,334 × 0.20 = ₱666.80
// Total tax: 937.50 + 666.80 = ₱1,604.30
```

---

## 12. Other Deductions

```javascript
/**
 * Calculate other deductions
 *
 * @param {Array} deductions - Array of deduction objects
 * @returns {number} Total other deductions
 */
function calculateOtherDeductions(deductions) {
  let total = 0;

  for (const deduction of deductions) {
    if (deduction.is_active) {
      // Check if deduction applies to this period
      const today = new Date();
      const startDate = new Date(deduction.start_date);
      const endDate = deduction.end_date ? new Date(deduction.end_date) : null;

      if (today >= startDate && (!endDate || today <= endDate)) {
        total += deduction.amount;
      }
    }
  }

  return total;
}
```

---

## 13. Loan Deductions

```javascript
/**
 * Calculate loan deductions and update loan balances
 *
 * @param {Array} loans - Array of active loan objects
 * @param {number} payrollItemId - Current payroll item ID
 * @returns {Object} Total deductions and payment records
 */
function calculateLoanDeductions(loans, payrollItemId) {
  let totalDeductions = 0;
  const payments = [];

  for (const loan of loans) {
    if (
      loan.status === "active" &&
      loan.payments_made < loan.number_of_payments
    ) {
      const amountToPay = loan.monthly_amortization;
      const newBalance = loan.remaining_balance - amountToPay;

      totalDeductions += amountToPay;

      // Record payment
      payments.push({
        loan_id: loan.id,
        payroll_item_id: payrollItemId,
        amount_paid: amountToPay,
        balance_after_payment: Math.max(0, newBalance),
      });

      // Update loan (would be done in service layer)
      // loan.payments_made++
      // loan.remaining_balance = Math.max(0, newBalance)
      // if (loan.remaining_balance === 0) loan.status = 'paid'
    }
  }

  return { totalDeductions, payments };
}
```

---

## 14. Net Pay Calculation

```javascript
/**
 * Calculate net pay
 *
 * @param {number} grossPay - Gross pay
 * @param {number} totalDeductions - Total deductions
 * @returns {number} Net pay
 */
function calculateNetPay(grossPay, totalDeductions) {
  return grossPay - totalDeductions;
}
```

---

## Complete Payroll Computation Example

```javascript
/**
 * Complete payroll computation for one employee
 *
 * @param {Object} employee - Employee object
 * @param {Array} attendance - Attendance records for period
 * @param {Array} allowances - Active allowances
 * @param {Array} bonuses - Bonuses for this period
 * @param {Array} deductions - Active deductions
 * @param {Array} loans - Active loans
 * @returns {Object} Computed payroll item
 */
function computePayroll(
  employee,
  attendance,
  allowances,
  bonuses,
  deductions,
  loans
) {
  // 1. Calculate days worked
  const daysWorked = calculateDaysWorked(attendance);

  // 2. Calculate basic pay
  const basicPay = calculateBasicPay(employee.basic_salary, daysWorked);

  // 3. Calculate overtime
  const overtimeHours = attendance.reduce(
    (sum, rec) => sum + (rec.overtime_hours || 0),
    0
  );
  const hourlyRate = employee.basic_salary / 8;
  const overtimePay = calculateOvertimePay(hourlyRate, overtimeHours);

  // 4. Calculate holiday pay
  let holidayPay = 0;
  attendance
    .filter((rec) => rec.is_holiday)
    .forEach((rec) => {
      holidayPay += calculateHolidayPay(
        employee.basic_salary,
        rec.holiday_type,
        rec.status === "present",
        rec.regular_hours + rec.overtime_hours
      );
    });

  // 5. Calculate night differential
  const nightHours = attendance.reduce(
    (sum, rec) => sum + (rec.night_differential_hours || 0),
    0
  );
  const nightDifferential = calculateNightDifferential(hourlyRate, nightHours);

  // 6. Calculate allowances
  const allowanceAmounts = calculateAllowances(allowances);

  // 7. Calculate bonuses
  const bonusAmounts = {
    taxable: bonuses
      .filter((b) => b.is_taxable)
      .reduce((sum, b) => sum + b.amount, 0),
    nonTaxable: bonuses
      .filter((b) => !b.is_taxable)
      .reduce((sum, b) => sum + b.amount, 0),
    total: bonuses.reduce((sum, b) => sum + b.amount, 0),
  };

  // 8. Calculate gross pay
  const grossPay = calculateGrossPay({
    basicPay,
    overtimePay,
    holidayPay,
    nightDifferential,
    allowances: allowanceAmounts.total,
    bonuses: bonusAmounts.total,
  });

  // 9. Convert to monthly for government contributions
  const semiMonthlyGross = grossPay;
  const monthlyGross = semiMonthlyGross * 2;

  // 10. Calculate government contributions
  const sssContribution = calculateSSS(monthlyGross) / 2; // Divide by 2 for semi-monthly
  const philHealthContribution = calculatePhilHealth(monthlyGross) / 2;
  const pagIbigContribution = calculatePagIBIG(monthlyGross) / 2;

  // 11. Calculate taxable income
  const taxableIncome = calculateTaxableIncome({
    grossPay,
    sss: sssContribution,
    philHealth: philHealthContribution,
    pagIbig: pagIbigContribution,
    nonTaxableAllowances: allowanceAmounts.nonTaxable,
    nonTaxableBonuses: bonusAmounts.nonTaxable,
  });

  // 12. Calculate withholding tax
  const withholdingTax = calculateWithholdingTax(taxableIncome);

  // 13. Calculate other deductions
  const otherDeductions = calculateOtherDeductions(deductions);

  // 14. Calculate loan deductions
  const loanResult = calculateLoanDeductions(loans, null); // payrollItemId set later

  // 15. Calculate total deductions
  const totalDeductions =
    sssContribution +
    philHealthContribution +
    pagIbigContribution +
    withholdingTax +
    otherDeductions +
    loanResult.totalDeductions;

  // 16. Calculate net pay
  const netPay = calculateNetPay(grossPay, totalDeductions);

  return {
    employee_id: employee.id,
    basic_rate: employee.basic_salary,
    days_worked: daysWorked,
    basic_pay: basicPay,
    overtime_hours: overtimeHours,
    overtime_pay: overtimePay,
    holiday_pay: holidayPay,
    night_differential: nightDifferential,
    total_allowances: allowanceAmounts.total,
    total_bonuses: bonusAmounts.total,
    gross_pay: grossPay,
    sss_contribution: sssContribution,
    philhealth_contribution: philHealthContribution,
    pagibig_contribution: pagIbigContribution,
    withholding_tax: withholdingTax,
    total_other_deductions: otherDeductions,
    total_loan_deductions: loanResult.totalDeductions,
    total_deductions: totalDeductions,
    net_pay: netPay,
    loan_payments: loanResult.payments,
  };
}
```

---

## Sample Payroll Calculation

### Employee Profile

- **Name**: Juan Dela Cruz
- **Position**: Sales Associate
- **Department**: Sales
- **Employment Type**: Regular
- **Daily Rate**: ₱600
- **Period**: January 1-15, 2025 (1st half)

### Attendance Summary

- Days worked: 12 days
- Overtime hours: 8 hours
- Night differential hours: 0
- Holidays worked: 0

### Allowances

- Transportation: ₱500 (taxable)
- Meal: ₱300 (non-taxable)

### Deductions

- SSS Loan: ₱500/month (₱250 semi-monthly)

### Computation

```
1. Basic Pay = ₱600 × 12 = ₱7,200.00

2. Overtime Pay = (₱600 / 8) × 8 × 1.25 = ₱75 × 8 × 1.25 = ₱750.00

3. Holiday Pay = ₱0 (no holidays)

4. Night Differential = ₱0 (no night hours)

5. Allowances = ₱500 + ₱300 = ₱800.00

6. Bonuses = ₱0

7. GROSS PAY = ₱7,200 + ₱750 + ₱800 = ₱8,750.00

8. Monthly Gross (for contributions) = ₱8,750 × 2 = ₱17,500

9. SSS = ₱787.50 / 2 = ₱393.75

10. PhilHealth = (₱17,500 × 0.04 / 2) / 2 = ₱175.00

11. Pag-IBIG = (₱17,500 × 0.02) / 2 = ₱175.00

12. Taxable Income = ₱8,750 - ₱393.75 - ₱175 - ₱175 - ₱300 (non-taxable allowance)
    = ₱7,706.25

13. Withholding Tax = ₱0 (below ₱10,417 threshold)

14. Other Deductions = ₱0

15. Loan Deduction = ₱250.00

16. TOTAL DEDUCTIONS = ₱393.75 + ₱175 + ₱175 + ₱0 + ₱0 + ₱250 = ₱993.75

17. NET PAY = ₱8,750.00 - ₱993.75 = ₱7,756.25
```

---

## Special Cases & Considerations

### 1. Minimum Wage Earners

```javascript
/**
 * Check if employee is minimum wage earner (tax-exempt)
 *
 * @param {number} dailyRate
 * @param {string} region
 * @returns {boolean}
 */
function isMinimumWageEarner(dailyRate, region) {
  // Example: NCR minimum wage as of 2025 (update as needed)
  const minimumWages = {
    NCR: 610,
    Region_I: 470,
    Region_III: 490,
    // ... other regions
  };

  return dailyRate <= (minimumWages[region] || 0);
}

// If minimum wage earner, withholding tax = 0
```

### 2. 13th Month Pay Calculation

```javascript
/**
 * Calculate 13th month pay
 *
 * @param {number} totalBasicSalary - Total basic salary for the year
 * @returns {number} 13th month pay
 */
function calculate13thMonthPay(totalBasicSalary) {
  return totalBasicSalary / 12;
}

// Tax treatment: First ₱90,000 is tax-exempt, excess is taxable
```

### 3. Pro-rated Deductions for New Employees

```javascript
/**
 * Pro-rate monthly deductions for partial month
 *
 * @param {number} monthlyAmount
 * @param {number} daysWorked
 * @param {number} workingDaysInPeriod
 * @returns {number}
 */
function prorateDeduction(monthlyAmount, daysWorked, workingDaysInPeriod) {
  return (monthlyAmount / workingDaysInPeriod) * daysWorked;
}
```

### 4. Undertime Deduction

```javascript
/**
 * Calculate undertime deduction
 *
 * @param {number} hourlyRate
 * @param {number} undertimeHours
 * @returns {number}
 */
function calculateUndertimeDeduction(hourlyRate, undertimeHours) {
  return hourlyRate * undertimeHours;
}

// Deduct from basic pay
```

### 5. Late Deduction

```javascript
/**
 * Calculate late deduction
 *
 * @param {number} hourlyRate
 * @param {number} lateMinutes
 * @returns {number}
 */
function calculateLateDeduction(hourlyRate, lateMinutes) {
  const minuteRate = hourlyRate / 60;
  return minuteRate * lateMinutes;
}

// Deduct from basic pay
```

---

## Validation Rules

1. **Gross pay cannot be negative**
2. **Net pay cannot be negative** (flag for review if deductions exceed gross)
3. **SSS, PhilHealth, Pag-IBIG** must be within valid ranges
4. **Tax computation** must use correct table for period
5. **Loan deductions** cannot exceed remaining balance
6. **Days worked** cannot exceed working days in period
7. **Overtime hours** should be reviewed if excessive

---

## Rounding Rules

- All monetary amounts: Round to 2 decimal places
- Days worked: Round to 2 decimal places
- Hours: Round to 2 decimal places
- Percentages: Use exact values in calculation, round final result

---

## Next Steps

See `06-DEVELOPMENT-ROADMAP.md` for phased implementation plan.
