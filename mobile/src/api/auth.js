import { api, setAuthToken } from './client';

let token;

// The assessment has one screen, so development bootstraps the seeded account.
// Replace this with a login screen and secure storage before production release.
export async function ensureSession() {
  if (token) return token;
  const email = process.env.EXPO_PUBLIC_DEMO_EMAIL;
  const password = process.env.EXPO_PUBLIC_DEMO_PASSWORD;
  if (!email || !password) {
    throw new Error('Set EXPO_PUBLIC_DEMO_EMAIL and EXPO_PUBLIC_DEMO_PASSWORD in mobile/.env.');
  }
  const response = await api.post('/login', {
    email,
    password,
  });
  token = response.data.token;
  setAuthToken(token);
  return token;
}
