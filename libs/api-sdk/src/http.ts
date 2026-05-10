import axios, {
  AxiosError,
  AxiosRequestConfig,
  AxiosResponse,
} from 'axios';

export const sdkAxios = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
});

export interface SdkConfig {
  baseURL?: string;
  headers?: Record<string, string>;
}

export const configureSdk = ({ baseURL, headers }: SdkConfig): void => {
  if (baseURL) sdkAxios.defaults.baseURL = baseURL;
  if (headers)
    sdkAxios.defaults.headers.common = {
      ...sdkAxios.defaults.headers.common,
      ...headers,
    };
};

export type SdkRequestConfig = AxiosRequestConfig;
export type SdkResponse<T> = AxiosResponse<T>;
export type SdkError<T = unknown> = AxiosError<T>;

export const customInstance = <T>(
  config: SdkRequestConfig,
  options?: SdkRequestConfig
): Promise<T> => {
  const source = axios.CancelToken.source();
  const promise = sdkAxios
    .request<T>({ ...config, ...options, cancelToken: source.token })
    .then(({ data }) => data);

  (promise as Promise<T> & { cancel?: () => void }).cancel = () => {
    source.cancel('Query was cancelled');
  };

  return promise;
};

export default customInstance;
