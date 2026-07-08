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

  const personnelPalette = [
    '#2563eb',
    '#22c55e',
    '#f97316',
    '#8b5cf6',
    '#06b6d4',
    '#ef4444',
    '#84cc16',
    '#ec4899',
    '#14b8a6',
    '#f59e0b',
    '#6366f1',
    '#0f766e',
    '#be123c',
    '#a16207',
    '#475569',
    '#7c3aed',
    '#0284c7',
    '#65a30d',
    '#db2777',
    '#dc2626',
    '#0891b2',
    '#ca8a04',
    '#4f46e5',
    '#16a34a'
  ];

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

  function renderPersonnelLegend(positions) {
    const legend = document.getElementById('personnelChartLegend');
    if (!legend) return;

    legend.innerHTML = '';
    const safePositions = Array.isArray(positions) ? positions : [];
    if (!safePositions.length) {
      legend.innerHTML = '<div class="text-center text-muted py-2">ไม่มีข้อมูล</div>';
      return;
    }

    safePositions.forEach((item, index) => {
      const row = document.createElement('div');
      row.className = 'personnel-legend-item';

      const swatch = document.createElement('span');
      swatch.className = 'personnel-legend-swatch';
      swatch.style.backgroundColor = personnelPalette[index % personnelPalette.length];

      const label = document.createElement('span');
      label.className = 'personnel-legend-label';
      label.textContent = item.position;

      const count = document.createElement('strong');
      count.className = 'personnel-legend-count';
      count.textContent = `${item.count} คน`;

      row.append(swatch, label, count);
      legend.appendChild(row);
    });
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

  function radialOptions() {
    return {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 450 },
      layout: {
        padding: 8
      },
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            usePointStyle: true,
            pointStyle: 'circle',
            boxWidth: 8,
            boxHeight: 8,
            padding: 9,
            color: '#334155',
            font: {
              family: 'Sarabun, Tahoma, sans-serif',
              size: 11,
              weight: '600'
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
      }
    };
  }

  function renderOnetSubjectChart(academic, levelKey = 'p6') {
    if (!hasCanvas('onetSubjectChart') || !academic?.onetByLevel?.[levelKey]) return;
    const years = ['2566', '2567', '2568'];
    const level = academic.onetByLevel[levelKey];
    const colors = [palette.blue, palette.green, palette.purple];

    drawChart('onetSubjectChart', {
      type: 'bar',
      data: {
        labels: academic.onetSubjects.map((subject) => subject.label),
        datasets: years.map((year, index) => ({
          label: year,
          data: academic.onetSubjects.map((subject) => level.years[year][subject.key]),
          backgroundColor: colors[index],
          borderRadius: 8,
          maxBarThickness: 34,
          borderSkipped: false
        }))
      },
      options: {
        ...baseOptions(),
        scales: {
          x: { grid: { display: false }, ticks: { color: '#475569' } },
          y: { beginAtZero: true, suggestedMax: 100, grid: { color: 'rgba(148, 163, 184, 0.16)' }, ticks: { callback: (value) => `${value}` } }
        }
      }
    });
  }

  function renderNtTrendChart(academic) {
    if (!hasCanvas('ntTrendChart') || !academic?.ntTrend) return;
    const years = ['2566', '2567', '2568'];
    const colors = [palette.blue, palette.green, palette.purple];

    drawChart('ntTrendChart', {
      type: 'bar',
      data: {
        labels: academic.ntDomains.map((domain) => domain.label),
        datasets: years.map((year, index) => ({
          label: year,
          data: academic.ntDomains.map((domain) => academic.ntTrend[year][domain.key]),
          backgroundColor: colors[index],
          borderRadius: 8,
          maxBarThickness: 34,
          borderSkipped: false
        }))
      },
      options: {
        ...baseOptions(),
        scales: {
          x: { grid: { display: false }, ticks: { color: '#475569' } },
          y: { beginAtZero: true, suggestedMax: 100, grid: { color: 'rgba(148, 163, 184, 0.16)' }, ticks: { callback: (value) => `${value}` } }
        }
      }
    });
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
      const personnelPositions = Array.isArray(data.personnel.byPosition) ? data.personnel.byPosition : [];
      renderPersonnelLegend(personnelPositions);
      if (personnelPositions.length) {
        drawChart('personnelChart', {
          type: 'doughnut',
          data: {
            labels: personnelPositions.map((x) => x.position),
            datasets: [{
              data: personnelPositions.map((x) => x.count),
              backgroundColor: personnelPositions.map((_, index) => personnelPalette[index % personnelPalette.length]),
              borderColor: '#ffffff',
              borderWidth: 2,
              hoverOffset: 8
            }]
          },
          options: {
            ...radialOptions(),
            plugins: {
              ...radialOptions().plugins,
              legend: { display: false }
            },
            cutout: '54%',
            radius: '100%'
          }
        });
      }
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

    if (hasCanvas('achievementChart') && data.academic) {
      const achievement = [
        ...data.academic.achievementPrimary,
        ...data.academic.achievementSecondary
      ];
      drawChart('achievementChart', {
        type: 'bar',
        data: {
          labels: achievement.map((x) => x.grade),
          datasets: [{
            label: 'ผลสัมฤทธิ์เฉลี่ย',
            data: achievement.map((x) => x.average),
            backgroundColor: [palette.blue, palette.green, palette.yellow, palette.purple, palette.cyan, palette.navy],
            borderRadius: 10,
            maxBarThickness: 34,
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
            y: { beginAtZero: true, suggestedMax: 100, grid: { color: 'rgba(148, 163, 184, 0.16)' }, ticks: { callback: (value) => `${value}%` } }
          }
        }
      });
    }

    if (data.academic) {
      renderOnetSubjectChart(data.academic, 'p6');
      renderNtTrendChart(data.academic);

      document.addEventListener('academic:onetLevelChanged', (changeEvent) => {
        renderOnetSubjectChart(data.academic, changeEvent.detail.level);
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

    document.querySelectorAll('[data-bs-toggle="pill"], [data-bs-toggle="tab"]').forEach((trigger) => {
      trigger.addEventListener('shown.bs.tab', () => {
        if (!window.__informationCharts) return;
        Object.values(window.__informationCharts).forEach((chart) => chart.resize());
      });
    });
  });
})();
