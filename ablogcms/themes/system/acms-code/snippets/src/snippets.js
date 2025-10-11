hljs.highlightAll();

document.addEventListener('DOMContentLoaded', () => {
  const code = document.querySelector('pre') || document.querySelector('textarea');

  code.addEventListener('click', function () {
    const range = document.createRange();
    range.selectNodeContents(this);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
  });
});
