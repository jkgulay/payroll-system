/**
 * Excel Export Utility using ExcelJS
 * Secure alternative to xlsx package
 */
import ExcelJS from "exceljs";

/**
 * Export data to Excel file
 * @param {Object} options - Export options
 * @param {string} options.filename - Output filename (without extension)
 * @param {Array} options.sheets - Array of sheet configurations
 * @param {string} options.sheets[].name - Sheet name
 * @param {Array} options.sheets[].columns - Column definitions [{ header: 'Name', key: 'name', width: 20 }]
 * @param {Array} options.sheets[].data - Data rows
 * @param {Object} options.sheets[].styles - Optional styling configuration
 */
export async function exportToExcel({ filename, sheets }) {
  const workbook = new ExcelJS.Workbook();

  // Set workbook properties
  workbook.creator = "Construction Payroll System";
  workbook.created = new Date();
  workbook.modified = new Date();

  // Create sheets
  for (const sheetConfig of sheets) {
    const worksheet = workbook.addWorksheet(sheetConfig.name);

    // Set columns
    worksheet.columns = sheetConfig.columns;

    // Add rows
    worksheet.addRows(sheetConfig.data);

    // Style header row
    const headerRow = worksheet.getRow(1);
    headerRow.font = { bold: true, size: 12 };
    headerRow.fill = {
      type: "pattern",
      pattern: "solid",
      fgColor: { argb: "FF4CAF50" }, // Green background
    };
    headerRow.alignment = { vertical: "middle", horizontal: "center" };
    headerRow.height = 20;

    // Auto-fit columns (set minimum width)
    worksheet.columns.forEach((column) => {
      if (!column.width) {
        column.width = 15;
      }
    });

    // Apply additional styles if provided
    if (sheetConfig.styles) {
      applyCustomStyles(worksheet, sheetConfig.styles);
    }
  }

  // Generate buffer and trigger download
  const buffer = await workbook.xlsx.writeBuffer();
  const blob = new Blob([buffer], {
    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  });

  // Create download link
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = `${filename}.xlsx`;
  link.click();

  // Cleanup
  URL.revokeObjectURL(link.href);
}

/**
 * Apply custom styles to worksheet
 * @param {ExcelJS.Worksheet} worksheet
 * @param {Object} styles
 */
function applyCustomStyles(worksheet, styles) {
  if (styles.alternateRows) {
    worksheet.eachRow((row, rowNumber) => {
      if (rowNumber > 1 && rowNumber % 2 === 0) {
        row.fill = {
          type: "pattern",
          pattern: "solid",
          fgColor: { argb: "FFF5F5F5" }, // Light gray
        };
      }
    });
  }
}

/**
 * Export employee list to Excel
 * @param {Array} employees - Array of employee objects
 */
export async function exportEmployees(employees) {
  const columns = [
    { header: "Employee ID", key: "employee_id", width: 15 },
    { header: "Last Name", key: "last_name", width: 20 },
    { header: "First Name", key: "first_name", width: 20 },
    { header: "Middle Name", key: "middle_name", width: 20 },
    { header: "Position", key: "position", width: 25 },
    { header: "Department", key: "department", width: 20 },
    { header: "Status", key: "employment_status", width: 15 },
    { header: "Hire Date", key: "hire_date", width: 15 },
    { header: "Basic Rate", key: "basic_rate", width: 15 },
  ];

  const data = employees.map((emp) => ({
    employee_id: emp.employee_id,
    last_name: emp.last_name,
    first_name: emp.first_name,
    middle_name: emp.middle_name || "",
    position: emp.position,
    department: emp.department?.name || "",
    employment_status: emp.employment_status,
    hire_date: emp.hire_date,
    basic_rate: emp.basic_rate,
  }));

  await exportToExcel({
    filename: `Employees_${new Date().toISOString().split("T")[0]}`,
    sheets: [
      {
        name: "Employees",
        columns,
        data,
        styles: { alternateRows: true },
      },
    ],
  });
}

/**
 * Export payroll report to Excel
 * @param {Object} payroll - Payroll object
 * @param {Array} items - Payroll items
 */
export async function exportPayrollReport(payroll, items) {
  // Summary sheet
  const summaryColumns = [
    { header: "Field", key: "field", width: 30 },
    { header: "Value", key: "value", width: 30 },
  ];

  const summaryData = [
    { field: "Payroll Number", value: payroll.payroll_number },
    {
      field: "Period",
      value: `${payroll.period_start} to ${payroll.period_end}`,
    },
    { field: "Payment Date", value: payroll.payment_date },
    { field: "Status", value: payroll.status },
    { field: "Total Employees", value: items.length },
    {
      field: "Total Gross Pay",
      value: `₱${parseFloat(payroll.total_gross_pay).toLocaleString()}`,
    },
    {
      field: "Total Deductions",
      value: `₱${parseFloat(payroll.total_deductions).toLocaleString()}`,
    },
    {
      field: "Total Net Pay",
      value: `₱${parseFloat(payroll.total_net_pay).toLocaleString()}`,
    },
  ];

  // Details sheet
  const detailColumns = [
    { header: "Employee ID", key: "employee_id", width: 15 },
    { header: "Employee Name", key: "employee_name", width: 30 },
    { header: "Basic Pay", key: "basic_pay", width: 15 },
    { header: "Overtime", key: "overtime_pay", width: 15 },
    { header: "Allowances", key: "total_allowances", width: 15 },
    { header: "Gross Pay", key: "gross_pay", width: 15 },
    { header: "SSS", key: "sss_contribution", width: 12 },
    { header: "PhilHealth", key: "philhealth_contribution", width: 12 },
    { header: "Pag-IBIG", key: "pagibig_contribution", width: 12 },
    { header: "Tax", key: "withholding_tax", width: 12 },
    { header: "Other Deductions", key: "total_other_deductions", width: 15 },
    { header: "Total Deductions", key: "total_deductions", width: 15 },
    { header: "Net Pay", key: "net_pay", width: 15 },
  ];

  const detailData = items.map((item) => ({
    employee_id: item.employee.employee_id,
    employee_name: `${item.employee.last_name}, ${item.employee.first_name}`,
    basic_pay: parseFloat(item.basic_pay),
    overtime_pay: parseFloat(item.overtime_pay),
    total_allowances: parseFloat(item.total_allowances),
    gross_pay: parseFloat(item.gross_pay),
    sss_contribution: parseFloat(item.sss_contribution),
    philhealth_contribution: parseFloat(item.philhealth_contribution),
    pagibig_contribution: parseFloat(item.pagibig_contribution),
    withholding_tax: parseFloat(item.withholding_tax),
    total_other_deductions: parseFloat(item.total_other_deductions),
    total_deductions: parseFloat(item.total_deductions),
    net_pay: parseFloat(item.net_pay),
  }));

  await exportToExcel({
    filename: `Payroll_${payroll.payroll_number}_${
      new Date().toISOString().split("T")[0]
    }`,
    sheets: [
      {
        name: "Summary",
        columns: summaryColumns,
        data: summaryData,
      },
      {
        name: "Details",
        columns: detailColumns,
        data: detailData,
        styles: { alternateRows: true },
      },
    ],
  });
}

/**
 * Export attendance report to Excel
 * @param {Array} attendance - Array of attendance records
 * @param {Object} filters - Applied filters
 */
export async function exportAttendanceReport(attendance, filters = {}) {
  const columns = [
    { header: "Date", key: "date", width: 15 },
    { header: "Employee ID", key: "employee_id", width: 15 },
    { header: "Employee Name", key: "employee_name", width: 30 },
    { header: "Time In", key: "time_in", width: 12 },
    { header: "Time Out", key: "time_out", width: 12 },
    { header: "Hours Worked", key: "hours_worked", width: 15 },
    { header: "Overtime", key: "overtime_hours", width: 12 },
    { header: "Status", key: "status", width: 15 },
    { header: "Remarks", key: "remarks", width: 30 },
  ];

  const data = attendance.map((record) => ({
    date: record.date,
    employee_id: record.employee.employee_id,
    employee_name: `${record.employee.last_name}, ${record.employee.first_name}`,
    time_in: record.time_in || "N/A",
    time_out: record.time_out || "N/A",
    hours_worked: record.hours_worked || 0,
    overtime_hours: record.overtime_hours || 0,
    status: record.status,
    remarks: record.remarks || "",
  }));

  const filterInfo =
    Object.keys(filters).length > 0
      ? `_${Object.entries(filters)
          .map(([k, v]) => `${k}-${v}`)
          .join("_")}`
      : "";

  await exportToExcel({
    filename: `Attendance_Report${filterInfo}_${
      new Date().toISOString().split("T")[0]
    }`,
    sheets: [
      {
        name: "Attendance",
        columns,
        data,
        styles: { alternateRows: true },
      },
    ],
  });
}

export default {
  exportToExcel,
  exportEmployees,
  exportPayrollReport,
  exportAttendanceReport,
};
