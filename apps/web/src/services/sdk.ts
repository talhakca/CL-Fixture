import { configureSdk } from '@champions-league-fixture/api-sdk';

const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api';

configureSdk({ baseURL });
