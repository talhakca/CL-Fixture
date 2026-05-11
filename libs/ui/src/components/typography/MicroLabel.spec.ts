import { render } from '@testing-library/vue';
import MicroLabel from './MicroLabel.vue';

describe('MicroLabel', () => {
  it('renders a span with the slot content', () => {
    const { container, getByText } = render(MicroLabel, {
      slots: { default: 'Attack' },
    });
    expect(container.querySelector('span')).not.toBeNull();
    expect(getByText('Attack')).toBeTruthy();
  });

  it('applies the base typography classes', () => {
    const { container } = render(MicroLabel, {
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('span')!;
    for (const c of [
      'text-xs',
      'uppercase',
      'tracking-wide',
      'text-muted-foreground',
    ]) {
      expect(el.classList.contains(c)).toBe(true);
    }
  });

  it('merges a consumer-provided class (e.g., block, w-16)', () => {
    const { container } = render(MicroLabel, {
      props: { class: 'block w-16' },
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('span')!;
    expect(el.classList.contains('block')).toBe(true);
    expect(el.classList.contains('w-16')).toBe(true);
    expect(el.classList.contains('uppercase')).toBe(true);
  });
});
