export type ScrollAnimationOptions = {
  delay?: number;
  repeat?: boolean;
  inViewClass?: string;
  animationClass?: string;
};

export const dispatchScrollAnimation = (context: HTMLElement, mark: string, options: ScrollAnimationOptions): void => {
  const targets = context.querySelectorAll<HTMLElement>(mark);
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        const el = entry.target as HTMLElement;
        const delay = parseInt(el.dataset.delay || String(options.delay || 0), 10);
        const repeat = el.dataset.repeat === 'true' || options.repeat === true;
        const inViewClass = el.dataset.inView ?? options.inViewClass ?? '';

        if (entry.isIntersecting) {
          setTimeout(() => {
            if (inViewClass) {
              el.classList.add(inViewClass);
            }
          }, delay);

          if (!repeat) {
            observer.unobserve(el);
          }
        } else if (repeat) {
          if (inViewClass) {
            el.classList.remove(inViewClass);
          }
        }
      });
    },
    { threshold: 0.1 }
  );

  targets.forEach((el) => {
    const animationClass = el.dataset.animation ?? options.animationClass ?? '';
    if (animationClass) {
      el.classList.add(animationClass);
    }
    observer.observe(el);
  });
};

export default dispatchScrollAnimation;
