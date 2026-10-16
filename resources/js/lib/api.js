import axios from 'axios';

const api = axios.create({
    baseURL: '/',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Add CSRF token to all requests
api.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Request interceptor
api.interceptors.request.use(config => {
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