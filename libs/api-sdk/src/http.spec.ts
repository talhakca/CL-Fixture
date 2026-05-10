import { describe, it, expect, vi, beforeEach } from 'vitest';
import { sdkAxios, configureSdk, customInstance } from './http';

describe('configureSdk', () => {
  beforeEach(() => {
    sdkAxios.defaults.baseURL = 'http://localhost:8000/api';
    sdkAxios.defaults.headers.common = {};
  });

  it('updates baseURL on the shared axios instance', () => {
    configureSdk({ baseURL: 'https://api.example.com' });
    expect(sdkAxios.defaults.baseURL).toBe('https://api.example.com');
  });

  it('merges headers without dropping existing ones', () => {
    sdkAxios.defaults.headers.common['X-Existing'] = 'keep';
    configureSdk({ headers: { Authorization: 'Bearer token' } });
    expect(sdkAxios.defaults.headers.common['X-Existing']).toBe('keep');
    expect(sdkAxios.defaults.headers.common['Authorization']).toBe(
      'Bearer token'
    );
  });
});

describe('customInstance', () => {
  it('unwraps axios response data', async () => {
    const spy = vi
      .spyOn(sdkAxios, 'request')
      .mockResolvedValue({ data: { status: 'ok' } });

    const result = await customInstance<{ status: string }>({
      url: '/health',
      method: 'GET',
    });

    expect(result).toEqual({ status: 'ok' });
    expect(spy).toHaveBeenCalledOnce();
    spy.mockRestore();
  });

  it('exposes a cancel function on the returned promise', () => {
    vi.spyOn(sdkAxios, 'request').mockResolvedValue({ data: null });

    const promise = customInstance({ url: '/health', method: 'GET' });
    expect(typeof (promise as Promise<unknown> & { cancel?: () => void }).cancel).toBe(
      'function'
    );
  });
});
