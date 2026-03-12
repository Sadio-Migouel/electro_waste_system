async function renderNavbar() {
    const navbarHost = document.getElementById("navbar");

    if (!navbarHost) {
        return null;
    }

    let user = null;

    try {
        if (typeof getCurrentUser === "function") {
            user = await getCurrentUser();
        } else {
            const result = await api("auth_me.php");
            user = result.user;
        }
    } catch (error) {
        if (error.status === 401) {
            return null;
        }

        throw error;
    }

    navbarHost.innerHTML = `
        <nav class="navbar navbar-expand-lg app-navbar">
            <div class="container-fluid px-0">
                <a class="navbar-brand fw-semibold" href="./dashboard.html">E-Waste</a>
                <div class="d-flex align-items-center gap-3 ms-auto">
                    <div id="navbarNotificationBell" class="position-relative d-none" aria-label="Notifications">
                        <span class="small text-secondary" aria-hidden="true">Alerts</span>
                        <span id="navbarNotificationCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger d-none">0</span>
                    </div>
                    <div class="text-end">
                        <div class="navbar-user-name">${user.full_name}</div>
                        <div class="navbar-user-role">${user.role}</div>
                    </div>
                    <button id="logoutButton" class="btn btn-outline-danger btn-sm" type="button">Logout</button>
                </div>
            </div>
        </nav>
    `;

    const logoutButton = document.getElementById("logoutButton");

    logoutButton.addEventListener("click", async () => {
        try {
            await api("auth_logout.php", "POST");
        } finally {
            localStorage.removeItem("ewaste_user");
            redirectTo("./login.html");
        }
    });

    loadNavbarNotifications().catch(() => {
        const bell = document.getElementById("navbarNotificationBell");

        if (bell) {
            bell.classList.add("d-none");
        }
    });

    return user;
}

async function loadNavbarNotifications() {
    const bell = document.getElementById("navbarNotificationBell");
    const countBadge = document.getElementById("navbarNotificationCount");

    if (!bell || !countBadge) {
        return;
    }

    const result = await api("notifications_list.php");
    const unreadCount = (result.notifications || []).filter((item) => Number(item.is_read) === 0).length;

    bell.classList.remove("d-none");
    countBadge.textContent = String(unreadCount);
    countBadge.classList.toggle("d-none", unreadCount === 0);
}
