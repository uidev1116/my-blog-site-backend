export const hasClass = (el: HTMLElement, className: string) => {
  if (el.classList) {
    return el.classList.contains(className);
  }
  return new RegExp(`(^| )${className}( |$)`, 'gi').test(el.className);
};

export const addClass = (element: HTMLElement, className: string) => {
  if (element.classList) {
    element.classList.add(className);
  } else {
    element.className += ` ${className}`;
  }
};

export const removeClass = (element: HTMLElement, className: string) => {
  if (element.classList) {
    element.classList.remove(className);
  } else {
    element.className = element.className.replace(
      new RegExp(`(^|\\b)${className.split(' ').join('|')}(\\b|$)`, 'gi'),
      ' '
    );
  }
};

export const wrap = (el: HTMLElement, tag: string) => {
  const parent = document.createElement(tag);
  el.parentElement?.insertBefore(parent, el);
  parent.appendChild(el);
  return parent;
};

export const remove = (element: HTMLElement) => {
  if (element && element.parentNode) {
    element.parentNode.removeChild(element);
  }
};

const displayStates: Map<HTMLElement, string> = new Map();

export function hide(element: HTMLElement): void {
  const { display } = getComputedStyle(element);

  if (display !== 'none') {
    displayStates.set(element, display);
    element.style.display = 'none';
  }
}

export function show(element: HTMLElement): void {
  const { display } = getComputedStyle(element);

  if (display === 'none') {
    element.style.display = displayStates.get(element) || 'block';
  }
}
