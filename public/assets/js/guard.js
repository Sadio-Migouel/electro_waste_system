let currentUserPromise = null;

async function getCurrentUser(forceRefresh = false) {
    if (forceRefresh || !currentUserPromise) {
        currentUserPromise = api("auth_me.php").then((result) => result.user);
    }

    return currentUserPromise;
}

function redirectTo(path) {
    window.location.href = path;
}

async function requireRole(role) {
    try {
        const user = await getCurrentUser();

        if (user.role !== role) {
            redirectTo("./dashboard.html");
            return null;
        }

        return user;
    } catch (error) {
        if (error.status === 401) {
            redirectTo("./login.html");
            return null;
        }

        throw error;
    }
}

