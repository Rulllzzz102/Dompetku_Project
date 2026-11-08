
document.addEventListener("DOMContentLoaded", function () {

    // =========================
    // TANGGAL HARI INI
    // =========================

    const dateElement = document.getElementById("currentDate");

    if (dateElement) {
        const today = new Date();

        dateElement.textContent = today.toLocaleDateString("id-ID", {
            weekday: "long",
            day: "numeric",
            month: "long",
            year: "numeric"
        });
    }


    // =========================
    // PENCARIAN TRANSAKSI
    // =========================

    const searchInput = document.getElementById("searchInput");

    if (searchInput) {
        searchInput.addEventListener("input", function () {

            const keyword = this.value.toLowerCase();

            const rows = document.querySelectorAll(
                "#transactionTable tr"
            );

            rows.forEach(function (row) {

                const transaction = row.textContent.toLowerCase();

                row.style.display = transaction.includes(keyword)
                    ? ""
                    : "none";

            });

        });
    }


    // =========================
    // SIDEBAR COLLAPSE
    // =========================

    const menuToggle = document.getElementById("menuToggle");
    const sidebarBackdrop = document.getElementById("sidebarBackdrop");

    if (menuToggle) {
        const updateSidebarExpanded = function () {
            const isExpanded = document.body.classList.contains("sidebar-open");
            menuToggle.setAttribute("aria-expanded", String(isExpanded));
        };

        updateSidebarExpanded();

        menuToggle.addEventListener("click", function () {
            document.body.classList.toggle("sidebar-open");
            updateSidebarExpanded();
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                document.body.classList.remove("sidebar-open");
                updateSidebarExpanded();
            }
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener("click", function () {
            document.body.classList.remove("sidebar-open");
            if (menuToggle) {
                menuToggle.setAttribute("aria-expanded", "false");
            }
        });
    }

    document.querySelectorAll(".menu a").forEach(function (link) {
        link.addEventListener("click", function () {
            document.body.classList.remove("sidebar-open");
            if (menuToggle) {
                menuToggle.setAttribute("aria-expanded", "false");
            }
        });
    });

    const passwordInput = document.getElementById("password");
    const passwordToggle = document.querySelector("[data-password-toggle]");

    if (passwordInput && passwordToggle) {
        passwordToggle.addEventListener("click", function () {
            const shouldShow = passwordInput.type === "password";
            passwordInput.type = shouldShow ? "text" : "password";
            passwordToggle.textContent = shouldShow ? "Sembunyikan" : "Tampilkan";
            passwordToggle.setAttribute("aria-pressed", String(shouldShow));
        });
    }

    const amountInput = document.getElementById("amount");

    if (amountInput) {
        document.querySelectorAll("[data-add-amount]").forEach(function (button) {
            button.addEventListener("click", function () {
                const currentAmount = Number(amountInput.value || 0);
                amountInput.value = String(currentAmount + Number(button.dataset.addAmount));
                amountInput.dispatchEvent(new Event("input", { bubbles: true }));
            });
        });

        const clearAmountButton = document.querySelector("[data-clear-amount]");
        if (clearAmountButton) {
            clearAmountButton.addEventListener("click", function () {
                amountInput.value = "";
                amountInput.dispatchEvent(new Event("input", { bubbles: true }));
            });
        }
    }

    document.querySelectorAll("form[data-confirm]").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll("[data-print-page]").forEach(function (button) {
        button.addEventListener("click", function () {
            window.print();
        });
    });

});
