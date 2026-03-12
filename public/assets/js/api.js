const API_BASE = "../Api";

async function api(path, method = "GET", body) {
    const options = {
        method,
        headers: {
            "Content-Type": "application/json"
        },
        credentials: "include"
    };

    if (body !== undefined) {
        options.body = JSON.stringify(body);
    }

    const response = await fetch(`${API_BASE}/${path}`, options);
    const rawText = await response.text();

    let data;

    try {
        data = rawText ? JSON.parse(rawText) : {};
    } catch (error) {
        throw new Error("Invalid JSON response");
    }

    if (!response.ok) {
        const requestError = new Error(data.error || "Request failed");
        requestError.status = response.status;
        requestError.data = data;
        throw requestError;
    }

    return data;
}

