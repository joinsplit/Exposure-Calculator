$(document).ready(function () {
    $('#trades').DataTable({
      responsive: true,
      columnDefs: [{
          responsivePriority: 1,
          targets: 0
        },
        {
          responsivePriority: 10001,
          targets: 4
        },
        {
          responsivePriority: 2,
          targets: -2
        }
      ],
      "searching": false,
      "paging": true, // Enabled pagination
      "ordering": true,
      "info": false,
      "pageLength": 10, // Set default number of rows per page (optional)
      order: [
          [9, 'asc'] // Default descending sort on column 10 (index 9)
      ]
    });
    
    $('#realized_pnl_daily').DataTable({
      responsive: true,
      columnDefs: [{
          responsivePriority: 1,
          targets: 0
        },
        {
          responsivePriority: 10001,
          targets: 4
        },
        {
          responsivePriority: 2,
          targets: -2
        }
      ],
      "searching": false,
      "paging": true,
      "ordering": true,
      "info": false,
      "pageLength": 10, // Set default number of rows per page (optional)
      order: [
          [0, 'desc'] // Default descending sort on column 1 (index 0)
      ]
    });
        $('#realized_pnl_monthly').DataTable({
      responsive: true,
      columnDefs: [{
          responsivePriority: 1,
          targets: 0
        },
        {
          responsivePriority: 10001,
          targets: 4
        },
        {
          responsivePriority: 2,
          targets: -2
        }
      ],
      "searching": false,
      "paging": true,
      "ordering": true,
      "info": false,
      "pageLength": 10, // Default number of rows per page
      order: [
          [0, 'desc'] // Default sorting on the first column (index 0)
      ]
    });

    var table_trades = $('#trades').DataTable();  
    table_trades.on('click', 'tr', function () {
      var data = table_trades.row(this).data();

      document.getElementById('current_asset').value = data[1];
      document.getElementById('current_price').value = data[3];
      document.getElementById('add_price').value = data[4];

      document.getElementById('profit_asset').value = data[1];
      document.getElementById('profit_entry').value = data[3];
      document.getElementById('profit_price').value = data[4];
      
  });
});
