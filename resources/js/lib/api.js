import axios from 'axios';
import { csrfHeaders } from './http';

const api = axios.create({
    baseURL: '/',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Request interceptor
api.interceptors.request.use(config => {
    // Read a fresh CSRF token from the rotating XSRF-TOKEN cookie on every
    // request — the static <meta> token goes stale on SPA pages and is the
    // usual cause of HTTP 419 "CSRF token mismatch".
    Object.assign(config.headers, csrfHeaders());

    // Add authorization token if exists
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Response interceptor
api.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            // Handle unauthorized
            window.location.href = '/login';
        }
        if (error.response?.status === 403) {
            // Handle forbidden
            alert('You do not have permission to access this page.');
        }
        return Promise.reject(error);
    }
);

export default api;