import { v4 as uuidv4 } from 'uuid';
import { fetchClient, isAbortError } from '../../../lib/fetch-client';

/**
 * e.g.
 * import IncrementalSearch from './lib/incremental-search';
 * const is = new IncrementalSearch();
 * is.addRequest('selector', endpoint, callback);
 */
export default class IncrementalSearch {
  /**
   * Constructor
   *
   * @param options
   */
  constructor(options) {
    const defaults = {
      interval: 200,
      keyName: 'word',
    };
    const opt = { ...defaults, ...options };

    this.timer = null;
    this.cancel = null;
    this.source = {};
    this.interval = opt.interval;
    this.keyName = opt.keyName;
    this.requests = [];
    this.onKeyUp = this.onKeyUp.bind(this);
  }

  /**
   * @param selector
   * @param endpoint
   * @param callback
   */
  addRequest(selector, endpoint, callback = () => {}) {
    const input = typeof selector === 'string' ? document.querySelector(selector) : selector;
    input.addEventListener('keyup', this.onKeyUp);
    input.setAttribute('data-request-id', uuidv4());
    this.requests.push({ input, endpoint, callback });
  }

  onKeyUp(event) {
    const { value } = event.target;
    event.preventDefault();
    clearTimeout(this.timer);
    const { endpoint, callback } = this.requests.find(
      (req) => req.input.dataset.requestId === event.target.dataset.requestId
    );
    this.timer = setTimeout(() => {
      if (this.abortController) {
        this.abortController.abort();
      }
      this.request(endpoint, value).then((json) => {
        if (Array.isArray(json)) {
          callback(json);
        }
      });
    }, this.interval);
  }

  /**
   * @param endpoint
   * @param value
   * @return Promise
   */
  request(endpoint, value) {
    const params = new URLSearchParams();
    params.append('ACMS_POST_Search_Items', true);
    params.append(this.keyName, value);
    params.append('formToken', window.csrfToken);

    this.abortController = new AbortController();

    return new Promise((resolve, reject) => {
      fetchClient
        .post(endpoint, params, {
          signal: this.abortController.signal,
        })
        .then((response) => {
          resolve(response.data);
        })
        .catch((thrown) => {
          if (!isAbortError(thrown)) {
            reject(thrown.message);
          }
        });
    });
  }

  destroy() {
    clearTimeout(this.timer);
    if (this.abortController) {
      this.abortController.abort();
    }
    this.requests.forEach((request) => {
      request.input.removeEventListener('keyup', this.onKeyUp);
    });
  }
}
