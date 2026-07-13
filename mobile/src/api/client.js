import axios from 'axios';

export const api = axios.create({
  baseURL: process.env.EXPO_PUBLIC_API_URL || 'http://10.0.2.2:8000/api',
  timeout: 10000,
});

export const setAuthToken = (token) => {
  api.defaults.headers.common.Authorization = token ? `Bearer ${token}` : undefined;
};
