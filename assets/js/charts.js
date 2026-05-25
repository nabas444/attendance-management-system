/**
 * charts.js — Chart.js 4 helper factories
 */

/**
 * Creates a doughnut chart showing attendance percentage.
 * @param {string} canvasId
 * @param {number} pct  0-100
 * @param {string} label
 * @returns {Chart}
 */
function createDoughnutChart(canvasId, pct, label = "Attendance") {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return null;
  return new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: [label, "Absent"],
      datasets: [
        {
          data: [pct, 100 - pct],
          backgroundColor: [pct >= 75 ? "#22c55e" : "#ef4444", "#e2e8f0"],
          borderWidth: 0,
          hoverOffset: 4,
        },
      ],
    },
    options: {
      cutout: "72%",
      plugins: {
        legend: { display: false },
        tooltip: { enabled: true },
      },
      animation: { duration: 800 },
    },
    plugins: [
      {
        id: "centerText",
        afterDraw(chart) {
          const {
            ctx: c,
            chartArea: { left, top, right, bottom },
          } = chart;
          const cx = (left + right) / 2;
          const cy = (top + bottom) / 2;
          c.save();
          c.font = "bold 22px Segoe UI, sans-serif";
          c.fillStyle = pct >= 75 ? "#22c55e" : "#ef4444";
          c.textAlign = "center";
          c.textBaseline = "middle";
          c.fillText(pct + "%", cx, cy);
          c.restore();
        },
      },
    ],
  });
}

/**
 * Creates a bar chart for department-wise attendance.
 * @param {string} canvasId
 * @param {string[]} labels
 * @param {number[]} values
 * @returns {Chart}
 */
function createBarChart(canvasId, labels, values) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return null;
  return new Chart(ctx, {
    type: "bar",
    data: {
      labels,
      datasets: [
        {
          label: "Attendance %",
          data: values,
          backgroundColor: values.map((v) => (v >= 75 ? "#3a52a0" : "#ef4444")),
          borderRadius: 6,
          borderSkipped: false,
        },
      ],
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: { callback: (v) => v + "%" },
          grid: { color: "#f1f5f9" },
        },
        x: { grid: { display: false } },
      },
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => ctx.raw + "%" } },
      },
    },
  });
}

/**
 * Creates a line chart for attendance trend over time.
 * @param {string} canvasId
 * @param {string[]} labels  Date labels
 * @param {number[]} values  Percentage values
 * @returns {Chart}
 */
function createLineChart(canvasId, labels, values) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return null;
  return new Chart(ctx, {
    type: "line",
    data: {
      labels,
      datasets: [
        {
          label: "Attendance %",
          data: values,
          borderColor: "#3a52a0",
          backgroundColor: "rgba(58,82,160,0.1)",
          tension: 0.3,
          fill: true,
          pointBackgroundColor: "#3a52a0",
          pointRadius: 4,
        },
      ],
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: { callback: (v) => v + "%" },
          grid: { color: "#f1f5f9" },
        },
        x: { grid: { display: false } },
      },
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => ctx.raw + "%" } },
      },
    },
  });
}
