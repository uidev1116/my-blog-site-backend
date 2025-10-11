import { pushPending, removePending } from './store/store';

function splash(message: string) {
  pushPending({ type: 'splash', message });
  return removePending;
}

export default {
  splash,
};
