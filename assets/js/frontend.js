/**
 * Accessibility Audit Pro - Frontend JavaScript
 */

(function ($) {
  "use strict";

  // Main object
  const AAP = {
    nonce: "",
    ajaxUrl: "",
    packages: {},
    selectedPackage: "",
    discountApplied: null,

    init: function () {
      this.nonce =
        $(".aap-audit-container").data("nonce") ||
        $(".aap-preview-scanner").data("nonce");
      this.ajaxUrl = aapFrontend?.ajaxUrl || "/wp-admin/admin-ajax.php";

      this.initPackageSelection();
      this.initPreviewForm();
      this.initDiscountCode();
      this.initOrderForm();
      this.initPayPal();
      this.initStatusPolling();
    },

    // Package selection
    initPackageSelection: function () {
      const self = this;

      // Store package data
      $(".aap-pricing-card").each(function () {
        const key = $(this).data("package");
        self.packages[key] = {
          name: $(this).data("name"),
          price: parseFloat($(this).data("price")),
          pages: $(this).data("pages"),
        };
      });

      // Handle selection
      $(".aap-pricing-selectable .aap-pricing-card").on("click", function () {
        const $card = $(this);
        const packageKey = $card.data("package");

        // Update selection
        $(".aap-pricing-card").removeClass("aap-selected");
        $card.addClass("aap-selected");

        // Update buttons
        $(".aap-button-select")
          .removeClass("aap-button-selected")
          .text("Select");
        $card
          .find(".aap-button-select")
          .addClass("aap-button-selected")
          .text("Selected");

        // Update hidden field
        $("#aap-package-type").val(packageKey);
        self.selectedPackage = packageKey;

        // Update summary
        self.updateOrderSummary();
      });

      // Set initial selection
      const initialPackage = $("#aap-package-type").val();
      if (initialPackage) {
        self.selectedPackage = initialPackage;
        self.updateOrderSummary();
      }
    },

    // Update order summary
    updateOrderSummary: function () {
      const pkg = this.packages[this.selectedPackage];
      if (!pkg) return;

      $("#aap-summary-package").text(pkg.name);
      $("#aap-summary-pages").text(pkg.pages + " pages");

      let total = pkg.price;

      if (this.discountApplied) {
        if (this.discountApplied.type === "percentage") {
          total = total * (1 - this.discountApplied.amount / 100);
        } else {
          total = Math.max(0, total - this.discountApplied.amount);
        }

        $(".aap-discount-row").show();
        $("#aap-summary-discount").text("-$" + (pkg.price - total).toFixed(2));
      } else {
        $(".aap-discount-row").hide();
      }

      $("#aap-summary-total").text("$" + total.toFixed(2));
    },

    // Preview form
    initPreviewForm: function () {
      const self = this;

      $("#aap-preview-form, #aap-standalone-preview-form").on(
        "submit",
        function (e) {
          e.preventDefault();

          const $form = $(this);
          const $button = $form.find('button[type="submit"]');
          const $results = $form.siblings(".aap-preview-results");
          const url = $form.find('input[name="preview_url"]').val();

          // Show loading
          $button.prop("disabled", true);
          $button.find(".aap-button-text").text("Scanning...");
          $button.find(".aap-spinner").show();
          $results.hide();

          $.ajax({
            url: self.ajaxUrl,
            type: "POST",
            data: {
              action: "aap_get_preview",
              nonce: self.nonce,
              website_url: url,
            },
            success: function (response) {
              if (response.success) {
                self.displayPreviewResults($results, response.data);
                $("#aap-preview-cta").show();
              } else {
                self.showError($results, response.data.message);
              }
            },
            error: function () {
              self.showError($results, "An error occurred. Please try again.");
            },
            complete: function () {
              $button.prop("disabled", false);
              $button.find(".aap-button-text").text("Preview Scan");
              $button.find(".aap-spinner").hide();
            },
          });
        }
      );
    },

    // Display preview results
    displayPreviewResults: function ($container, data) {
      const scoreClass =
        data.score >= 8
          ? "score-good"
          : data.score >= 5
          ? "score-medium"
          : "score-poor";

      let html = `
                <div class="aap-preview-score">
                    <div class="aap-preview-score-circle ${scoreClass}">
                        <span style="font-size: 24px;">${data.score.toFixed(
                          1
                        )}</span>
                        <span style="font-size: 12px;">/10</span>
                    </div>
                    <div>
                        <strong>${data.total_issues}</strong> issues found<br>
                        <span style="color: #dc2626;">${
                          data.errors
                        } errors</span> • 
                        <span style="color: #d97706;">${
                          data.warnings
                        } warnings</span>
                    </div>
                </div>
            `;

      if (data.preview_issues && data.preview_issues.length > 0) {
        html += '<div class="aap-preview-issues">';
        html += '<h4 style="margin: 0 0 15px;">Sample Issues:</h4>';

        data.preview_issues.forEach(function (issue) {
          const iconClass = issue.severity === "error" ? "error" : "warning";
          const icon = issue.severity === "error" ? "✗" : "⚠";

          html += `
                        <div class="aap-preview-issue">
                            <span class="aap-issue-icon ${iconClass}">${icon}</span>
                            <span>${self.escapeHtml(issue.message)}</span>
                        </div>
                    `;
        });

        if (data.hidden_count > 0) {
          html += `
                        <p style="margin: 15px 0 0; padding: 15px; background: #f0f9ff; border-radius: 8px; text-align: center;">
                            <strong>${data.hidden_count} more issues</strong> found. 
                            <a href="#aap-order-form">Get a full report</a> to see all results.
                        </p>
                    `;
        }

        html += "</div>";
      }

      $container.html(html).show();
    },

    // Discount code
    initDiscountCode: function () {
      const self = this;

      $("#aap-apply-discount").on("click", function () {
        const code = $("#aap-discount-code").val().trim();
        const $result = $("#aap-discount-result");

        if (!code) {
          $result
            .removeClass("success")
            .addClass("error")
            .text("Please enter a discount code.");
          return;
        }

        $.ajax({
          url: self.ajaxUrl,
          type: "POST",
          data: {
            action: "aap_validate_discount",
            nonce: self.nonce,
            discount_code: code,
            package_type: self.selectedPackage,
          },
          success: function (response) {
            if (response.success) {
              $result
                .removeClass("error")
                .addClass("success")
                .text("✓ " + response.data.discount_text + " applied!");

              self.discountApplied = {
                code: code,
                type: response.data.discount_text.includes("%")
                  ? "percentage"
                  : "fixed",
                amount: parseFloat(response.data.discount_amount),
              };

              self.updateOrderSummary();
            } else {
              $result
                .removeClass("success")
                .addClass("error")
                .text(response.data.message);
              self.discountApplied = null;
              self.updateOrderSummary();
            }
          },
          error: function () {
            $result
              .removeClass("success")
              .addClass("error")
              .text("Error validating code. Please try again.");
          },
        });
      });
    },

    // Order form (for admin free access)
    initOrderForm: function () {
      const self = this;

      $("#aap-order-form").on("submit", function (e) {
        // Only handle for admin free access
        if ($("#aap-paypal-container").length > 0) {
          return; // PayPal handles this
        }

        e.preventDefault();

        const $form = $(this);
        const $button = $form.find('button[type="submit"]');

        $button.prop("disabled", true).text("Starting scan...");

        $.ajax({
          url: self.ajaxUrl,
          type: "POST",
          data: {
            action: "aap_create_order",
            nonce: self.nonce,
            package_type: self.selectedPackage,
            website_url: $("#aap-website-url").val(),
            customer_email: $("#aap-customer-email").val(),
            customer_name: $("#aap-customer-name").val(),
          },
          success: function (response) {
            if (response.success && response.data.admin_free) {
              window.location.href = response.data.redirect_url;
            } else if (response.success) {
              // Handle PayPal redirect
              window.location.href = response.data.approval_url;
            } else {
              alert(response.data.message);
              $button.prop("disabled", false).text("Start Free Audit");
            }
          },
          error: function () {
            alert("An error occurred. Please try again.");
            $button.prop("disabled", false).text("Start Free Audit");
          },
        });
      });
    },

    // PayPal integration
    initPayPal: function () {
      const self = this;

      if (
        typeof paypal === "undefined" ||
        !$("#paypal-button-container").length
      ) {
        return;
      }

      paypal
        .Buttons({
          style: {
            layout: "vertical",
            color: "blue",
            shape: "rect",
            label: "pay",
          },

          createOrder: function (data, actions) {
            // Validate form
            const websiteUrl = $("#aap-website-url").val();
            const email = $("#aap-customer-email").val();

            if (!websiteUrl || !email) {
              alert("Please fill in all required fields.");
              return;
            }

            return new Promise(function (resolve, reject) {
              $.ajax({
                url: self.ajaxUrl,
                type: "POST",
                data: {
                  action: "aap_create_order",
                  nonce: self.nonce,
                  package_type: self.selectedPackage,
                  website_url: websiteUrl,
                  customer_email: email,
                  customer_name: $("#aap-customer-name").val(),
                  discount_code: self.discountApplied
                    ? self.discountApplied.code
                    : "",
                },
                success: function (response) {
                  if (response.success) {
                    resolve(response.data.order_id);
                  } else {
                    alert(response.data.message);
                    reject(response.data.message);
                  }
                },
                error: function () {
                  alert("Error creating order. Please try again.");
                  reject("Server error");
                },
              });
            });
          },

          onApprove: function (data, actions) {
            return new Promise(function (resolve, reject) {
              $.ajax({
                url: self.ajaxUrl,
                type: "POST",
                data: {
                  action: "aap_capture_payment",
                  nonce: self.nonce,
                  order_id: data.orderID,
                },
                success: function (response) {
                  if (response.success) {
                    window.location.href = response.data.redirect_url;
                  } else {
                    alert("Payment failed: " + response.data.message);
                  }
                  resolve();
                },
                error: function () {
                  alert("Error processing payment. Please contact support.");
                  reject();
                },
              });
            });
          },

          onError: function (err) {
            console.error("PayPal error:", err);
            alert("Payment error. Please try again or contact support.");
          },

          onCancel: function (data) {
            console.log("Payment cancelled");
          },
        })
        .render("#paypal-button-container");
    },

    // Status polling
    initStatusPolling: function () {
      const self = this;
      const $status = $(".aap-report-status");

      if (!$status.length) return;

      const status = $status.data("status");

      if (
        ["pending", "scanning", "processing", "generating"].includes(status)
      ) {
        self.pollStatus();
      }
    },

    pollStatus: function () {
      const self = this;
      const $status = $(".aap-report-status");
      const reportId = $status.data("report-id");
      const accessKey = $status.data("access-key");

      $.ajax({
        url: self.ajaxUrl,
        type: "POST",
        data: {
          action: "aap_check_report_status",
          report_id: reportId,
          access_key: accessKey,
        },
        success: function (response) {
          if (response.success) {
            const data = response.data;

            // Update progress bar
            $(".aap-progress-fill").css("width", data.progress + "%");
            $(".aap-progress-text").text(data.progress + "% Complete");

            // Update status message
            $(".aap-status-message").text(data.message);

            // Check if completed
            if (data.status === "completed") {
              location.reload();
            } else if (data.status === "failed") {
              location.reload();
            } else {
              // Poll again in 5 seconds
              setTimeout(function () {
                self.pollStatus();
              }, 5000);
            }
          }
        },
        error: function () {
          // Retry in 10 seconds
          setTimeout(function () {
            self.pollStatus();
          }, 10000);
        },
      });
    },

    // Helper: Show error
    showError: function ($container, message) {
      $container
        .html(
          '<p style="color: #dc2626;">⚠ ' + this.escapeHtml(message) + "</p>"
        )
        .show();
    },

    // Helper: Escape HTML
    escapeHtml: function (text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    AAP.init();
  });
})(jQuery);
