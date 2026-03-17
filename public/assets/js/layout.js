function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "\"": "&quot;",
        "'": "&#39;"
    }[char]));
}

function roleBadge(role) {
    const variants = {
        user: "secondary",
        collector: "warning",
        admin: "primary"
    };
    const variant = variants[role] || "dark";
    const label = role ? role.charAt(0).toUpperCase() + role.slice(1) : "Unknown";

    return `<span class="badge text-bg-${variant}">${escapeHtml(label)}</span>`;
}

function statusBadge(status) {
    const variants = {
        pending: "secondary",
        approved: "info",
        assigned: "primary",
        collected: "success",
        cancelled: "danger"
    };
    const variant = variants[status] || "dark";
    const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : "Unknown";

    return `<span class="badge text-bg-${variant}">${escapeHtml(label)}</span>`;
}

function itemsSummary(items) {
    if (!Array.isArray(items) || items.length === 0) {
        return "-";
    }

    return items.map((item) => `${item.name}(${item.qty})`).join(", ");
}

function initials(fullName) {
    return String(fullName || "")
        .split(" ")
        .map((part) => part.trim().charAt(0))
        .filter(Boolean)
        .slice(0, 2)
        .join("")
        .toUpperCase() || "EW";
}

const AVAILABLE_PAGES = {
    dashboard: true,
    request: true,
    track: true,
    profile: true,
    collector: true,
    admin: true,
    reports: true,
    admin_users: false
};

function menuItemsForRole(role) {
    if (role === "collector") {
        return [
            {
                key: "dashboard",
                label: "Dashboard",
                href: AVAILABLE_PAGES.collector ? "./collector.html" : "./dashboard.html",
                icon: "bi-speedometer2",
                enabled: AVAILABLE_PAGES.collector || AVAILABLE_PAGES.dashboard
            },
            {
                key: "assigned",
                label: "Assigned Pickups",
                href: "./collector.html",
                icon: "bi-truck",
                enabled: AVAILABLE_PAGES.collector
            },
            {
                key: "profile",
                label: "Profile",
                href: "./profile.html",
                icon: "bi-person-circle",
                enabled: AVAILABLE_PAGES.profile
            }
        ];
    }

    if (role === "admin") {
        return [
            {
                key: "dashboard",
                label: "Dashboard",
                href: AVAILABLE_PAGES.admin ? "./admin.html" : "./dashboard.html",
                icon: "bi-speedometer2",
                enabled: AVAILABLE_PAGES.admin || AVAILABLE_PAGES.dashboard
            },
            {
                key: "manage_requests",
                label: "Manage Requests",
                href: "./admin.html#manage-requests",
                icon: "bi-clipboard-check",
                enabled: AVAILABLE_PAGES.admin
            },
            {
                key: "manage_users",
                label: "Manage Users",
                href: AVAILABLE_PAGES.admin_users ? "./admin_users.html" : "./admin.html#manage-users",
                icon: "bi-people",
                enabled: AVAILABLE_PAGES.admin || AVAILABLE_PAGES.admin_users
            },
            {
                key: "reports",
                label: "Reports",
                href: "./reports.html",
                icon: "bi-graph-up-arrow",
                enabled: AVAILABLE_PAGES.reports
            },
            {
                key: "profile",
                label: "Profile",
                href: "./profile.html",
                icon: "bi-person-circle",
                enabled: AVAILABLE_PAGES.profile
            }
        ];
    }

    return [
        {
            key: "dashboard",
            label: "Dashboard",
            href: "./dashboard.html",
            icon: "bi-speedometer2",
            enabled: AVAILABLE_PAGES.dashboard
        },
        {
            key: "request",
            label: "Request Pickup",
            href: "./request.html",
            icon: "bi-plus-square",
            enabled: AVAILABLE_PAGES.request
        },
        {
            key: "track",
            label: "Track Requests",
            href: "./track.html",
            icon: "bi-list-check",
            enabled: AVAILABLE_PAGES.track
        },
        {
            key: "profile",
            label: "Profile",
            href: "./profile.html",
            icon: "bi-person-circle",
            enabled: AVAILABLE_PAGES.profile
        }
    ];
}

function renderNavLink(item, activeKey) {
    const classes = `sidebar-link ${item.key === activeKey ? "active" : ""} ${item.enabled ? "" : "disabled"}`.trim();
    const ariaDisabled = item.enabled ? "" : 'aria-disabled="true" tabindex="-1"';
    const href = item.enabled ? item.href : "#";

    return `
        <a class="${classes}" href="${href}" ${ariaDisabled}>
            <i class="bi ${item.icon}"></i>
            <span>${item.label}</span>
        </a>
    `;
}

function sidebarMarkup(user, activeKey) {
    return `
        <div class="d-flex flex-column h-100">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="brand-mark"><i class="bi bi-recycle"></i></span>
                <div>
                    <div class="fw-bold">E-Waste System</div>
                    <div class="sidebar-caption">Waste pickup portal</div>
                </div>
            </div>

            <div class="d-grid gap-2">
                ${menuItemsForRole(user.role).map((item) => renderNavLink(item, activeKey)).join("")}
            </div>

            <div class="sidebar-footer">
                <button type="button" class="btn sidebar-logout js-logout-button">
                    <i class="bi bi-box-arrow-right me-2"></i>
                    Logout
                </button>
            </div>
        </div>
    `;
}

function appShellMarkup(user, activeKey, pageTitle) {
    return `
        <div class="app-shell">
            <aside class="app-sidebar">
                ${sidebarMarkup(user, activeKey)}
            </aside>

            <div class="offcanvas offcanvas-start app-offcanvas" tabindex="-1" id="mobileSidebar">
                <div class="offcanvas-header">
                    <div class="d-flex align-items-center gap-3">
                        <span class="brand-mark"><i class="bi bi-recycle"></i></span>
                        <div>
                            <div class="fw-bold">E-Waste System</div>
                            <div class="sidebar-caption">Waste pickup portal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    ${sidebarMarkup(user, activeKey)}
                </div>
            </div>

            <div class="app-content">
                <header class="topbar">
                    <div class="topbar-inner d-flex justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-label="Open navigation">
                                <i class="bi bi-list"></i>
                            </button>
                            <div>
                                <div class="page-chip"><i class="bi bi-lightning-charge-fill"></i> E-Waste System</div>
                                <h1 class="topbar-title">${escapeHtml(pageTitle)}</h1>
                                <div class="topbar-subtitle">Manage pickups, requests, and account activity.</div>
                            </div>
                        </div>

                        <div class="topbar-user">
                            <span class="user-avatar">${escapeHtml(initials(user.full_name))}</span>
                            <div class="text-end">
                                <div class="fw-semibold">${escapeHtml(user.full_name)}</div>
                                <div>${roleBadge(user.role)}</div>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="page-content" id="pageOutlet"></main>
            </div>
        </div>
    `;
}

function layoutErrorMarkup(message) {
    return `<div class="alert alert-danger m-3" role="alert">${escapeHtml(message)}</div>`;
}

async function performLogout() {
    try {
        await api("auth_logout.php", "POST");
    } finally {
        localStorage.removeItem("user");
        localStorage.removeItem("ewaste_user");
        window.location.href = "./login.html";
    }
}

function bindLayoutEvents() {
    document.querySelectorAll(".js-logout-button").forEach((button) => {
        button.addEventListener("click", performLogout);
    });
}

async function initLayout(activeKey, pageTitle) {
    const app = document.getElementById("app");

    if (!app) {
        const message = "Missing #app container";
        console.error(message);
        document.body.insertAdjacentHTML("afterbegin", layoutErrorMarkup(message));
        throw new Error(message);
    }

    let user;

    try {
        const result = await api("auth_me.php");
        user = result.user;
    } catch (error) {
        if (error.status === 401) {
            window.location.href = "./login.html";
            return null;
        }

        console.error("Failed to load authenticated layout", error);
        app.innerHTML = layoutErrorMarkup(error.message || "Failed to load layout");
        throw error;
    }

    app.innerHTML = appShellMarkup(user, activeKey, pageTitle);
    bindLayoutEvents();

    const outlet = document.getElementById("pageOutlet");

    if (!outlet) {
        const message = "Missing #pageOutlet after layout injection";
        console.error(message);
        app.innerHTML = layoutErrorMarkup(message);
        throw new Error(message);
    }

    return user;
}
