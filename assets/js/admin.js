/**
 * Accessibility Audit Pro - Admin JavaScript
 */

(function ($) {
  "use strict";

  const AAPAdmin = {
    init: function () {
      this.initNewScanForm();
      this.initReportActions();
      this.initTestEmail();
      this.initDashboardCharts();
    },

    // New scan form
    initNewScanForm: function () {
      const self = this;

      $("#aap-admin-scan-form").on("submit", function (e) {
        e.preventDefault();

        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $result = $("#aap-scan-result");

        const websiteUrl = $("#website_url").val();
        const packageType = $("#package_type").val();

        if (!websiteUrl) {
          alert("Please enter a website URL.");
          return;
        }

        $button.prop("disabled", true).find(".dashicons").addClass("spin");
        $result.hide();

        $.ajax({
          url: aapAdmin.ajaxUrl,
          type: "POST",
          data: {
            action: "aap_admin_scan",
            nonce: aapAdmin.nonce,
            website_url: websiteUrl,
            package_type: packageType,
          },
          success: function (response) {
            if (response.success) {
              $result
                .removeClass("error")
                .addClass("success")
                .html(
                  "<p><strong>Success!</strong> " +
                    response.data.message +
                    "</p>" +
                    '<p><a href="' +
                    response.data.view_url +
                    '" class="button">View Report</a></p>'
                )
                .show();

              $("#website_url").val("");
            } else {
              $result
                .removeClass("success")
                .addClass("error")
                .html(
                  "<p><strong>Error:</strong> " + response.data.message + "</p>"
                )
                .show();
            }
          },
          error: function () {
            $result
              .removeClass("success")
              .addClass("error")
              .html(
                "<p><strong>Error:</strong> An error occurred. Please try again.</p>"
              )
              .show();
          },
          complete: function () {
            $button
              .prop("disabled", false)
              .find(".dashicons")
              .removeClass("spin");
          },
        });
      });
    },

    // Report actions (rescan, delete)
    initReportActions: function () {
      const self = this;

      // Rescan
      $(".aap-rescan-btn").on("click", function () {
        const reportId = $(this).data("report-id");

        if (!confirm("Are you sure you want to rescan this website?")) {
          return;
        }

        $.ajax({
          url: aapAdmin.ajaxUrl,
          type: "POST",
          data: {
            action: "aap_rescan_report",
            nonce: aapAdmin.nonce,
            report_id: reportId,
          },
          success: function (response) {
            if (response.success) {
              alert(response.data.message);
              location.reload();
            } else {
              alert("Error: " + response.data.message);
            }
          },
          error: function () {
            alert("An error occurred. Please try again.");
          },
        });
      });

      // Delete
      $(".aap-delete-btn").on("click", function () {
        const reportId = $(this).data("report-id");

        if (
          !confirm(
            "Are you sure you want to delete this report? This cannot be undone."
          )
        ) {
          return;
        }

        $.ajax({
          url: aapAdmin.ajaxUrl,
          type: "POST",
          data: {
            action: "aap_delete_report",
            nonce: aapAdmin.nonce,
            report_id: reportId,
          },
          success: function (response) {
            if (response.success) {
              alert(response.data.message);
              window.location.href = "admin.php?page=aap-reports";
            } else {
              alert("Error: " + response.data.message);
            }
          },
          error: function () {
            alert("An error occurred. Please try again.");
          },
        });
      });
    },

    // Test email
    initTestEmail: function () {
      $("#aap-send-test-email").on("click", function () {
        const $button = $(this);
        const $result = $("#aap-test-email-result");
        const email = prompt("Enter email address for test:");

        if (!email) return;

        $button.prop("disabled", true);
        $result.text("Sending...");

        $.ajax({
          url: aapAdmin.ajaxUrl,
          type: "POST",
          data: {
            action: "aap_send_test_email",
            nonce: aapAdmin.nonce,
            email: email,
          },
          success: function (response) {
            if (response.success) {
              $result.css("color", "green").text("✓ " + response.data.message);
            } else {
              $result.css("color", "red").text("✗ " + response.data.message);
            }
          },
          error: function () {
            $result.css("color", "red").text("✗ Error sending email");
          },
          complete: function () {
            $button.prop("disabled", false);
          },
        });
      });
    },

    // Dashboard charts
    initDashboardCharts: function () {
      if (typeof Chart === "undefined") return;

      const $scansChart = $("#aap-scans-chart");
      const $revenueChart = $("#aap-revenue-chart");

      if ($scansChart.length) {
        this.loadScansChart($scansChart);
      }

      if ($revenueChart.length) {
        this.loadRevenueChart($revenueChart);
      }
    },

    loadScansChart: function ($canvas) {
      // Placeholder - in production, load data via AJAX
      new Chart($canvas, {
        type: "line",
        data: {
          labels: ["Week 1", "Week 2", "Week 3", "Week 4"],
          datasets: [
            {
              label: "Scans",
              data: [12, 19, 15, 25],
              borderColor: "#07599c",
              backgroundColor: "rgba(7, 89, 156, 0.1)",
              fill: true,
              tension: 0.4,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              display: false,
            },
          },
          scales: {
            y: {
              beginAtZero: true,
            },
          },
        },
      });
    },

    loadRevenueChart: function ($canvas) {
      // Placeholder - in production, load data via AJAX
      new Chart($canvas, {
        type: "bar",
        data: {
          labels: ["5 Pages", "10 Pages", "25 Pages", "50 Pages", "100 Pages"],
          datasets: [
            {
              label: "Sales",
              data: [5, 12, 8, 3, 2],
              backgroundColor: [
                "rgba(7, 89, 156, 0.8)",
                "rgba(9, 225, 192, 0.8)",
                "rgba(34, 197, 94, 0.8)",
                "rgba(234, 179, 8, 0.8)",
                "rgba(239, 68, 68, 0.8)",
              ],
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              display: false,
            },
          },
          scales: {
            y: {
              beginAtZero: true,
            },
          },
        },
      });
    },
  };

  // Spinning animation for icons
  const style = document.createElement("style");
  style.textContent = `
        .dashicons.spin {
            animation: dashicons-spin 1s linear infinite;
        }
        @keyframes dashicons-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
  document.head.appendChild(style);

  // Initialize on document ready
  $(document).ready(function () {
    AAPAdmin.init();
  });
})(jQuery);
