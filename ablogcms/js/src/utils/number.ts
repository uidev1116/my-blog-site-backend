const formatter = new Intl.NumberFormat(navigator.languages as string[], { style: 'decimal' });

export function formatNumber(number: number | bigint): string {
  return formatter.format(number);
}
