import { render } from '@testing-library/vue';
import PageSubtitle from './PageSubtitle.vue';

describe('PageSubtitle', () => {
  it('renders a p with the slot content', () => {
    const { container, getByText } = render(PageSubtitle, {
      slots: { default: 'Four teams. Six weeks.' },
    });
    expect(container.querySelector('p')).not.toBeNull();
    expect(getByText('Four teams. Six weeks.')).toBeTruthy();
  });

  it('applies the base typography classes', () => {
    const { container } = render(PageSubtitle, {
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('p')!;
    for (const c of ['text-sm', 'text-muted-foreground']) {
      expect(el.classList.contains(c)).toBe(true);
    }
  });

  it('merges a consumer-provided class', () => {
    const { container } = render(PageSubtitle, {
      props: { class: 'italic' },
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('p')!;
    expect(el.classList.contains('italic')).toBe(true);
    expect(el.classList.contains('text-muted-foreground')).toBe(true);
  });
});
