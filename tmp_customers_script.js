
      const ADMIN_KEY = "";
      const downloadCsvBtn = document.getElementById("downloadCsv");
      const downloadJsonBtn = document.getElementById("downloadJson");
      const downloadStatus = document.getElementById("downloadStatus");
      const customerCount = document.getElementById("customerCount");

      const getKey = () => ADMIN_KEY;

      const getDownloadFilename = (response, fallback) => {
        const disposition = response.headers.get("Content-Disposition") || "";
        const match = disposition.match(/filename="?([^\";]+)"?/i);
        return match ? match[1] : fallback;
      };

      const downloadRequests = async (format) => {
        const key = getKey();
        if (!key) {
          downloadStatus.textContent = "Admin key required.";
          return;
        }
        downloadStatus.textContent = "";
        try {
          const response = await fetch(`../api/admin/export.php?format=${encodeURIComponent(format)}`, {
            headers: { "X-Admin-Key": key },
          });
          if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || "download");
          }
          const blob = await response.blob();
          const filename = getDownloadFilename(response, `booking-requests.${format}`);
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement("a");
          link.href = url;
          link.download = filename;
          document.body.appendChild(link);
          link.click();
          link.remove();
          window.URL.revokeObjectURL(url);
          downloadStatus.textContent = "Download ready.";
        } catch (_error) {
          downloadStatus.textContent = "Failed to download.";
        }
      };

      if (downloadCsvBtn) {
        downloadCsvBtn.addEventListener("click", () => downloadRequests("csv"));
      }
      if (downloadJsonBtn) {
        downloadJsonBtn.addEventListener("click", () => downloadRequests("json"));
      }

      document.querySelectorAll("[data-delete-key]").forEach((btn) => {
        btn.addEventListener("click", async () => {
          const key = getKey();
          if (!key) {
            downloadStatus.textContent = "Admin key required.";
            return;
          }
          const targetKey = btn.dataset.deleteKey || "";
          if (!targetKey) return;
          if (!confirm("Delete this customer from the directory?")) return;
          downloadStatus.textContent = "";
          try {
            const response = await fetch("../api/admin/delete-customer.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "X-Admin-Key": key,
              },
              body: JSON.stringify({ key: targetKey }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || "delete");
            const row = btn.closest("tr");
            if (row) row.remove();
            if (customerCount) {
              const current = Number(customerCount.textContent || 0);
              if (!Number.isNaN(current) && current > 0) {
                customerCount.textContent = String(current - 1);
              }
            }
            if (customerCount && Number(customerCount.textContent || 0) <= 0) {
              const tbody = document.querySelector("tbody");
              if (tbody && !tbody.querySelector("[data-empty-row]")) {
                const emptyRow = document.createElement("tr");
                emptyRow.dataset.emptyRow = "true";
                emptyRow.innerHTML = '<td colspan="7" class="muted">No customers yet.</td>';
                tbody.appendChild(emptyRow);
              }
            }
            downloadStatus.textContent = "Customer deleted.";
          } catch (_error) {
            downloadStatus.textContent = "Failed to delete customer.";
          }
        });
      });
    