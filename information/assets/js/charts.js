(function () {
  const palette = {
    blue: '#2563eb',
    green: '#22c55e',
    yellow: '#f59e0b',
    red: '#ef4444',
    purple: '#8b5cf6',
    cyan: '#06b6d4',
    navy: '#1e3a8a',
    slate: '#64748b'
  };

  function drawChart(id, config) {
    const canvas = document.getElementById(id);
    if (!canvas || !window.Chart) return;

    if (!window.__informationCharts) {
      window.__informationCharts = {};
    }
    if (window.__informationCharts[id]) {
      window.__informationCharts[id].destroy();
    }

    window.__informationCharts[id] = new Chart(canvas, config);
  }

  function hasCanvas(id) {
    return Boolean(document.getElementById(id));
  }

  function baseOptions() {
    return {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 450 },
      layout: {
        padding: 4
      },
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            usePointStyle: true,
            pointStyle: 'circle',
            boxWidth: 8,
            boxHeight: 8,
            padding: 10,
            color: '#334155',
            font: {
              family: 'Sarabun, Tahoma, sans-serif',
              size: 11,
              weight: '500'
            }
          }
        },
        tooltip: {
          backgroundColor: 'rgba(15, 23, 42, 0.95)',
          titleFont: {
            family: 'Sarabun, Tahoma, sans-serif',
            size: 12,
            weight: '700'
          },
          bodyFont: {
            family: 'Sarabun, Tahoma, sans-serif',
            size: 11
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(148, 163, 184, 0.14)' },
          ticks: {
            color: '#64748b',
            maxRotation: 0,
            autoSkip: true,
            font: {
              family: 'Sarabun, Tahoma, sans-serif',
              size: 10
            }
          }
        },
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(148, 163, 184, 0.14)' },
          ticks: {
            color: '#64748b',
            precision: 0,
            font: {
              family: 'Sarabun, Tahoma, sans-serif',
              size: 10
            }
          }
        }
      }
    };
  }

  document.addEventListener('information:dataReady', (event) => {
    const data = event.detail;

    if (hasCanvas('homeSummaryChart') && data.school && data.students && data.personnel) {
      drawChart('homeSummaryChart', {
        type: 'bar',
        data: {
          labels: ['นักเรียน', 'บุคลากร', 'ห้องเรียน'],
          datasets: [{
            label: 'จำนวน',
            data: [data.students.totalStudents, data.personnel.totalPersonnel, data.school.classroomCount],
            backgroundColor: [palette.blue, palette.green, palette.yellow],
            borderRadius: 10,
            maxBarThickness: 42,
            borderSkipped: false
          }]
        },
        options: {
          ...baseOptions(),
          plugins: {
            ...baseOptions().plugins,
            legend: { display: false }
          },
          scales: {
            x: { grid: { display: false }, ticks: { color: '#475569' } },
            y: { beginAtZero: true, grid: { color: 'rgba(148, 163, 184, 0.16)' }, ticks: { precision: 0 } }
          }
        }
      });
    }

    if (hasCanvas('personnelChart') && data.personnel) {
      drawChart('personnelChart', {
        type: 'doughnut',
        data: {
          labels: data.personnel.byPosition.map((x) => x.position),
          datasets: [{
            data: data.personnel.byPosition.map((x) => x.count),
            backgroundColor: [palette.navy, palette.blue, palette.purple, palette.cyan],
            borderWidth: 0,
            hoverOffset: 6
          }]
        },
        options: {
          ...baseOptions(),
          cutout: '64%'
        }
      });
    }

    if (hasCanvas('studentsChart') && data.students) {
      drawChart('studentsChart', {
        type: 'pie',
        data: {
          labels: ['ชาย', 'หญิง'],
          datasets: [{
            data: [data.students.byGender.male, data.students.byGender.female],
            backgroundColor: [palette.blue, palette.purple],
            borderWidth: 0
          }]
        },
        options: {
          ...baseOptions(),
          plugins: {
            ...baseOptions().plugins,
            legend: { position: 'bottom' }
          }
        }
      });
    }

    if (hasCanvas('budgetChart') && data.budget) {
      drawChart('budgetChart', {
        type: 'bar',
        data: {
          labels: data.budget.items.map((x) => x.category),
          datasets: [{
            label: 'งบประมาณ (บาท)',
            data: data.budget.items.map((x) => x.amount),
            backgroundColor: [palette.blue, palette.green, palette.yellow, palette.purple],
            borderRadius: 10,
            maxBarThickness: 42,
            borderSkipped: false
          }]
        },
        options: {
          ...baseOptions(),
          scales: {
            x: { grid: { display: false }, ticks: { color: '#475569' } },
            y: { beginAtZero: true, grid: { color: 'rgba(148, 163, 184, 0.16)' }, ticks: { callback: (value) => `${value / 1000000}M` } }
          }
        }
      });
    }

    if (hasCanvas('sarChart') && data.school) {
      drawChart('sarChart', {
        type: 'line',
        data: {
          labels: data.school.sarIndicators.map((x) => x.name),
          datasets: [
            {
              label: 'เป้าหมาย',
              data: data.school.sarIndicators.map((x) => x.target),
              borderColor: palette.purple,
              backgroundColor: 'rgba(139, 92, 246, 0.12)',
              tension: 0.38,
              fill: true,
              pointRadius: 4,
              pointBackgroundColor: palette.purple
            },
            {
              label: 'ผลจริง',
              data: data.school.sarIndicators.map((x) => x.actual),
              borderColor: palette.blue,
              backgroundColor: 'rgba(37, 99, 235, 0.14)',
              tension: 0.38,
              fill: true,
              pointRadius: 4,
              pointBackgroundColor: palette.blue
            }
          ]
        },
        options: {
          ...baseOptions(),
          scales: {
            x: { grid: { display: false }, ticks: { color: '#475569' } },
            y: { beginAtZero: true, grid: { color: 'rgba(148, 163, 184, 0.16)' }, suggestedMax: 100 }
          }
        }
      });
    }
  });
})();
