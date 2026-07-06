(function () {
  function drawChart(id, config) {
    const canvas = document.getElementById(id);
    if (!canvas || !window.Chart) return;
    new Chart(canvas, config);
  }

  document.addEventListener('information:dataReady', (event) => {
    const data = event.detail;

    drawChart('homeSummaryChart', {
      type: 'bar',
      data: {
        labels: ['นักเรียน', 'บุคลากร', 'ห้องเรียน'],
        datasets: [{
          label: 'จำนวน',
          data: [data.students.totalStudents, data.personnel.totalPersonnel, data.school.classroomCount],
          backgroundColor: ['#2f6dbd', '#1e4f8f', '#5f8fc8']
        }]
      },
      options: { responsive: true, plugins: { legend: { display: false } } }
    });

    drawChart('personnelChart', {
      type: 'doughnut',
      data: {
        labels: data.personnel.byPosition.map((x) => x.position),
        datasets: [{
          data: data.personnel.byPosition.map((x) => x.count),
          backgroundColor: ['#0b2e59', '#1e4f8f', '#2f6dbd', '#6b96c9']
        }]
      },
      options: { responsive: true }
    });

    drawChart('studentsChart', {
      type: 'pie',
      data: {
        labels: ['ชาย', 'หญิง'],
        datasets: [{
          data: [data.students.byGender.male, data.students.byGender.female],
          backgroundColor: ['#1e4f8f', '#7aa3d2']
        }]
      },
      options: { responsive: true }
    });

    drawChart('budgetChart', {
      type: 'bar',
      data: {
        labels: data.budget.items.map((x) => x.category),
        datasets: [{
          label: 'งบประมาณ (บาท)',
          data: data.budget.items.map((x) => x.amount),
          backgroundColor: '#2f6dbd'
        }]
      },
      options: { responsive: true }
    });

    drawChart('sarChart', {
      type: 'line',
      data: {
        labels: data.school.sarIndicators.map((x) => x.name),
        datasets: [
          {
            label: 'เป้าหมาย',
            data: data.school.sarIndicators.map((x) => x.target),
            borderColor: '#7aa3d2',
            backgroundColor: '#7aa3d2'
          },
          {
            label: 'ผลจริง',
            data: data.school.sarIndicators.map((x) => x.actual),
            borderColor: '#1e4f8f',
            backgroundColor: '#1e4f8f'
          }
        ]
      },
      options: { responsive: true }
    });
  });
})();
