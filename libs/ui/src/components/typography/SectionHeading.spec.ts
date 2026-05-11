import { render } from '@testing-library/vue';
import SectionHeading from './SectionHeading.vue';

describe('SectionHeading', () => {
  it('renders an h2 with the slot content', () => {
    const { container, getByText } = render(SectionHeading, {
      slots: { default: 'Played Matches' },
    });
    expect(container.querySelector('h2')).not.toBeNull();
    expect(getByText('Played Matches')).toBeTruthy();
  });

  it('applies the base typography classes', () => {
    const { container } = render(SectionHeading, {
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('h2')!;
    for (const c of [
      'text-xs',
      'font-semibold',
      'uppercase',
      'tracking-wide',
      'text-muted-foreground',
    ]) {
      expect(el.classList.contains(c)).toBe(true);
    }
  });

  it('merges a consumer-provided class', () => {
    const { container } = render(SectionHeading, {
      props: { class: 'mb-4' },
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('h2')!;
    expect(el.classList.contains('mb-4')).toBe(true);
    expect(el.classList.contains('uppercase')).toBe(true);
  });
});
