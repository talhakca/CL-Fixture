import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { defineComponent, h } from 'vue';
import App from './App.vue';

describe('App', () => {
  it('renders the router outlet', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: '/',
          component: defineComponent({
            render: () => h('div', { 'data-testid': 'landing-stub' }, 'Landing'),
          }),
        },
      ],
    });
    await router.push('/');
    await router.isReady();

    const wrapper = mount(App, {
      global: { plugins: [router] },
    });

    expect(wrapper.text()).toContain('Landing');
  });
});
