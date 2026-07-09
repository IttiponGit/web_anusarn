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

  const sliceDataLabelsPlugin = {
    id: 'sliceDataLabels',
    afterDatasetsDraw(chart, args, pluginOptions) {
      const options = pluginOptions || {};
      const dataset = chart.data.datasets?.[0];
      const meta = chart.getDatasetMeta(0);
      if (!dataset || !meta || meta.hidden) return;

      const ctx = chart.ctx;
      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillStyle = options.color || '#ffffff';
      ctx.strokeStyle = options.strokeColor || 'rgba(15, 23, 42, 0.35)';
      ctx.lineWidth = options.strokeWidth ?? 3;
      ctx.font = options.font || '700 12px Sarabun, Tahoma, sans-serif';

      meta.data.forEach((element, dataIndex) => {
        const value = dataset.data[dataIndex];
        const context = {
          chart,
          dataset,
          datasetIndex: 0,
          dataIndex
        };
        const shouldDisplay = typeof options.display === 'function'
          ? options.display(context)
          : options.display !== false;
        if (!shouldDisplay) return;

        const label = typeof options.formatter === 'function'
          ? options.formatter(value, context)
          : String(value ?? '');
        if (!label) return;

        const position = element.tooltipPosition();
        String(label).split('\n').forEach((line, lineIndex, lines) => {
          const y = position.y + (lineIndex - (lines.length - 1) / 2) * 15;
          ctx.strokeText(line, position.x, y);
          ctx.fillText(line, position.x, y);
        });
      });

      ctx.restore();
    }
  };

  function sliceLabelFormatter(value, context) {
    const data = context.chart.data.datasets[0].data;
    const total = data.reduce((sum, item) => sum + Number(item || 0), 0);
    const numberValue = Number(value || 0);
    const percent = total > 0 ? (numberValue * 100 / total) : 0;
    return `${numberValue} คน\n${percent.toFixed(1)}%`;
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
          plugins: [sliceDataLabelsPlugin],
          options: {
            ...radialOptions(),
            plugins: {
              ...radialOptions().plugins,
              legend: { display: false },
              sliceDataLabels: {
                display(context) {
                  const data = context.chart.data.datasets[0].data;
                  const value = Number(data[context.dataIndex] || 0);
                  const total = data.reduce((sum, item) => sum + Number(item || 0), 0);
                  const percent = total > 0 ? (value * 100 / total) : 0;
                  return value >= 3 || percent >= 5;
                },
                formatter: sliceLabelFormatter
              }
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
        plugins: [sliceDataLabelsPlugin],
        options: {
          ...baseOptions(),
          plugins: {
            ...baseOptions().plugins,
            legend: { position: 'bottom' },
            sliceDataLabels: {
              display: true,
              formatter: sliceLabelFormatter
            }
          }
        }
      });
    }

    if (hasCanvas('budgetChart') && data.budget) {
      const budgetItems = Array.isArray(data.categories)
        ? data.categories
        : (Array.isArray(data.budget.items) ? data.budget.items : []);
      const budgetValue = (item, keys, fallback = '') => {
        for (const key of keys) {
          if (item && item[key] !== null && item[key] !== undefined && item[key] !== '') return item[key];
        }
        return fallback;
      };
      drawChart('budgetChart', {
        type: 'bar',
        data: {
          labels: budgetItems.map((x) => budgetValue(x, ['category', 'categoryName', 'name', 'label'], budgetValue(x.raw, ['category_name'], 'ไม่ระบุหมวด'))),
          datasets: [{
            label: 'งบประมาณ (บาท)',
            data: budgetItems.map((x) => Number(budgetValue(x, ['amount'], budgetValue(x.raw, ['amount'], 0)) || 0)),
            backgroundColor: budgetItems.map((x, index) => budgetValue(x, ['chartColor', 'chart_color'], budgetValue(x.raw, ['chart_color'], [
              palette.blue,
              palette.green,
              palette.yellow,
              palette.purple,
              palette.cyan,
              palette.navy,
              palette.red
            ][index % 7]))),
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

    if (hasCanvas('electricityLineChart') && data.electricityComparison) {
      const monthLabels = ['ต.ค.', 'พ.ย.', 'ธ.ค.', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.'];
      const readValue = (item, keys, fallback = null) => {
        for (const key of keys) {
          if (item && item[key] !== null && item[key] !== undefined && item[key] !== '') return item[key];
        }
        return fallback;
      };
      const toMonthNo = (item) => Number(readValue(item, ['monthNo', 'fiscalMonthNo'], readValue(item.raw, ['fiscal_month_no'], 0)));
      const toAmount = (item) => {
        const value = readValue(item, ['amount'], readValue(item.raw, ['amount'], null));
        if (value === null || value === undefined || value === '') return null;
        const numberValue = Number(String(value).replace(/,/g, ''));
        return Number.isFinite(numberValue) ? numberValue : null;
      };
      const seriesForYear = (year) => {
        const rows = Array.isArray(data.electricityComparison[year]) ? data.electricityComparison[year] : [];
        const byMonth = new Map(rows.map((item) => [toMonthNo(item), item]));
        return monthLabels.map((_, index) => {
          const item = byMonth.get(index + 1);
          return item ? toAmount(item) : null;
        });
      };
      const statusFor = (year, monthIndex) => {
        const rows = Array.isArray(data.electricityComparison[year]) ? data.electricityComparison[year] : [];
        const item = rows.find((row) => toMonthNo(row) === monthIndex + 1);
        return readValue(item, ['status', 'dataStatus'], readValue(item?.raw, ['data_status'], 'ยังไม่มีข้อมูล'));
      };

      drawChart('electricityLineChart', {
        type: 'line',
        data: {
          labels: monthLabels,
          datasets: [
            {
              label: 'ปีงบประมาณ 2568',
              data: seriesForYear('2568'),
              borderColor: palette.slate,
              backgroundColor: 'rgba(100, 116, 139, 0.12)',
              pointBackgroundColor: palette.slate,
              pointBorderColor: '#ffffff',
              pointRadius: 4,
              pointHoverRadius: 6,
              tension: 0.35,
              spanGaps: false
            },
            {
              label: 'ปีงบประมาณ 2569',
              data: seriesForYear('2569'),
              borderColor: palette.blue,
              backgroundColor: 'rgba(37, 99, 235, 0.12)',
              pointBackgroundColor: palette.blue,
              pointBorderColor: '#ffffff',
              pointRadius: 4,
              pointHoverRadius: 6,
              tension: 0.35,
              spanGaps: false
            }
          ]
        },
        options: {
          ...baseOptions(),
          interaction: {
            mode: 'index',
            intersect: false
          },
          plugins: {
            ...baseOptions().plugins,
            tooltip: {
              ...baseOptions().plugins.tooltip,
              callbacks: {
                title(items) {
                  return items?.[0]?.label || '';
                },
                label(context) {
                  const year = context.dataset.label.replace('ปีงบประมาณ ', '');
                  const value = context.raw;
                  if (value === null || value === undefined) {
                    return `${context.dataset.label}: ${statusFor(year, context.dataIndex)}`;
                  }
                  return `${context.dataset.label}: ${Number(value).toLocaleString('th-TH')} บาท`;
                }
              }
            }
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { color: '#475569' }
            },
            y: {
              beginAtZero: true,
              grid: { color: 'rgba(148, 163, 184, 0.16)' },
              ticks: {
                color: '#64748b',
                callback: (value) => Number(value).toLocaleString('th-TH')
              }
            }
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
