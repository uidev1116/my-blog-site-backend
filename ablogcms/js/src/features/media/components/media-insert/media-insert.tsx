import { applyMiddleware, legacy_createStore as createStore } from 'redux';
import { Provider } from 'react-redux';
import createSagaMiddleware from 'redux-saga';

import MediaInsertContainer from '../../containers/media-insert';
import reducer from '../../stores/reducer';
import saga from '../../stores/saga';

const sagaMiddleware = createSagaMiddleware();
const store = createStore(reducer, applyMiddleware(sagaMiddleware));

sagaMiddleware.run(saga);

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface MediaInsertProps extends React.ComponentProps<typeof MediaInsertContainer> {}

const EMPTY_FILES: File[] = [];

const MediaInsert = ({
  mode = 'insert',
  tab,
  radioMode,
  filetype = 'all',
  files = EMPTY_FILES,
  onInsert,
  onClose,
  isOpen,
  container,
}: MediaInsertProps) => (
  <Provider store={store}>
    <MediaInsertContainer
      mode={mode}
      radioMode={radioMode}
      tab={tab}
      filetype={filetype}
      files={files}
      onInsert={onInsert}
      onClose={onClose}
      isOpen={isOpen}
      container={container}
    />
  </Provider>
);

export default MediaInsert;
