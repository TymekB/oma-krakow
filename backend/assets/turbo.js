import { setFormMode, setProgressBarDelay } from '@hotwired/turbo';

setFormMode('optin');
setProgressBarDelay(100);

const usesSafeMethod = (form) => (form.getAttribute('method') ?? 'get').toLowerCase() === 'get';

const collectForms = (root) => {
  const found = root.tagName === 'FORM' ? [root] : [];

  if (typeof root.querySelectorAll === 'function') {
    found.push(...root.querySelectorAll('form'));
  }

  return found;
};

const enableTurboOnSafeForms = (root) => {
  collectForms(root)
    .filter((form) => !form.hasAttribute('data-turbo') && usesSafeMethod(form))
    .forEach((form) => form.setAttribute('data-turbo', 'true'));
};

new MutationObserver((mutations) => {
  mutations.forEach(({ addedNodes }) => {
    addedNodes.forEach((node) => {
      if (node.nodeType === Node.ELEMENT_NODE) {
        enableTurboOnSafeForms(node);
      }
    });
  });
}).observe(document.documentElement, { childList: true, subtree: true });

document.addEventListener('turbo:load', () => enableTurboOnSafeForms(document));
document.addEventListener('turbo:frame-load', (event) => enableTurboOnSafeForms(event.target));
