import { render } from '@testing-library/vue';
import PageTitle from './PageTitle.vue';

describe('PageTitle', () => {
  it('renders an h1 with the slot content', () => {
    const { container, getByText } = render(PageTitle, {
      slots: { default: 'Champions League' },
    });
    const el = container.querySelector('h1');
    expect(el).not.toBeNull();
    expect(getByText('Champions League')).toBeTruthy();
  });

  it('applies the base typography classes', () => {
    const { container } = render(PageTitle, {
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('h1')!;
    for (const c of ['text-2xl', 'md:text-3xl', 'font-bold', 'tracking-tight']) {
      expect(el.classList.contains(c)).toBe(true);
    }
  });

  it('merges a consumer-provided class', () => {
    const { container } = render(PageTitle, {
      props: { class: 'text-center' },
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('h1')!;
    expect(el.classList.contains('text-center')).toBe(true);
    expect(el.classList.contains('font-bold')).toBe(true);
  });
});
