import { Component, createRef } from 'react';

import Modal from '../../../../components/modal/modal';
import { Tabs, TabPanel } from '../../../../components/tabs/tabs';
import MediaList from '../media-list/media-list';
import MediaModal from '../media-modal/media-modal';
import MediaUploader from '../media-uploader/media-uploader';
import { MediaStateProps, MediaItem, MediaViewFileType } from '../../types';

interface MediaInsertModalProps
  extends MediaStateProps,
    Pick<React.ComponentProps<typeof Modal>, 'onClose' | 'isOpen' | 'container'> {
  tab?: 'upload' | 'select';
  mode?: 'edit' | 'insert';
  radioMode?: boolean;
  filetype?: MediaViewFileType;
  files?: File[];
  onInsert: (items: MediaItem[]) => void;
}

interface MediaInsertModalState {
  tabIndex: number;
  isMediaModalOpen: boolean;
}

export default class MediaInsertModal extends Component<MediaInsertModalProps, MediaInsertModalState> {
  uploaderRef: React.RefObject<InstanceType<typeof MediaUploader>>;

  constructor(props: MediaInsertModalProps) {
    super(props);
    this.state = {
      tabIndex: this.defaultTabIndex(),
      isMediaModalOpen: false,
    };
    const params = new URLSearchParams(window.location.hash.replace('#', ''));
    // e.g http://acms.org/bid/1/admin/media_index/#label=aaa,bbb&upload=true
    if (params.has('label')) {
      props.actions.setLabel(params.get('label') as string);
    }
    this.uploaderRef = createRef<InstanceType<typeof MediaUploader>>();
  }

  defaultTabIndex() {
    const params = new URLSearchParams(window.location.hash.replace('#', ''));
    const { tab } = this.props;
    if (params.get('upload') === 'true') {
      return 1;
    }

    if (tab === 'upload') {
      return 1;
    }
    return 0;
  }

  handleAfterOpen() {
    const { props } = this;
    const { actions } = props;
    actions.setFormToken(window.csrfToken);
    actions.fetchTagList();
  }

  componentDidUpdate(prevProps: MediaInsertModalProps) {
    if (prevProps.item !== this.props.item && this.props.item !== null) {
      this.setState({ isMediaModalOpen: true });
    }
    if (prevProps.isOpen !== this.props.isOpen && this.props.isOpen === true) {
      this.setState({ tabIndex: this.defaultTabIndex() });
    }
  }

  handleMediaModalClose() {
    this.setState({ isMediaModalOpen: false });
  }

  handleAfterMediaModalClose() {
    this.props.actions.setItem(null);
  }

  insertMedia() {
    const { items, onInsert } = this.props;
    const filters = items.filter((item) => item.checked);
    if (typeof onInsert === 'function') {
      onInsert(filters);
    }
    this.props.actions.setMediaList(items.map((item) => ({ ...item, checked: false })));
  }

  upsert() {
    const { onInsert } = this.props;
    this.uploaderRef.current?.uploadItems().then((results) => {
      onInsert(results);
    });
  }

  handleTabChange(tabIndex: number) {
    if (this.uploaderRef.current) {
      // タブ切り替え時にアップロード済みアイテムを削除
      this.uploaderRef.current.removeUploadedItems();
    }
    this.setState({ tabIndex });
  }

  render() {
    const { tabIndex, isMediaModalOpen } = this.state;
    const {
      items,
      actions,
      item,
      largeSize,
      formToken,
      archives,
      selectedTags,
      filetype,
      extensions,
      label,
      mode = 'insert',
      lastPage,
      config,
      tags,
      total,
      files,
      radioMode,
      isOpen,
      onClose,
    } = this.props;

    const footer =
      tabIndex === 1 ? (
        <button type="button" className="acms-admin-btn acms-admin-btn-primary" onClick={this.upsert.bind(this)}>
          {ACMS.i18n('media.upload_insert')}
        </button>
      ) : (
        <button type="button" className="acms-admin-btn acms-admin-btn-primary" onClick={this.insertMedia.bind(this)}>
          {ACMS.i18n('media.insert_selections')}
        </button>
      );

    return (
      <>
        <Modal
          className="acms-admin-media-modal"
          isOpen={isOpen}
          size="large"
          onClose={onClose}
          onAfterOpen={this.handleAfterOpen.bind(this)}
          aria-labelledby="acms-admin-media-insert-modal-title"
        >
          <Modal.Header>{ACMS.i18n('media.media_insert')}</Modal.Header>
          <Modal.Body tabContentScrollable>
            <Tabs index={tabIndex} onChange={this.handleTabChange.bind(this)}>
              <TabPanel label={ACMS.i18n('media.media_list')} id="media-list">
                <MediaList
                  extensions={extensions}
                  filetype={filetype}
                  items={items}
                  actions={actions}
                  mode={mode}
                  radioMode={radioMode}
                  lastPage={lastPage}
                  config={config}
                  archives={archives}
                  tags={tags}
                  total={total}
                  selectedTags={selectedTags}
                />
              </TabPanel>
              <TabPanel label={ACMS.i18n('media.upload')} id="media-upload">
                <MediaUploader
                  actions={actions}
                  largeSize={largeSize}
                  tags={tags}
                  label={label}
                  config={config}
                  ref={this.uploaderRef}
                  files={files}
                  showUploadButton={false}
                />
              </TabPanel>
            </Tabs>
          </Modal.Body>
          <Modal.Footer>{footer}</Modal.Footer>
        </Modal>
        <MediaModal
          isOpen={isMediaModalOpen}
          onClose={this.handleMediaModalClose.bind(this)}
          item={item}
          actions={actions}
          formToken={formToken}
          tags={tags}
          onAfterClose={this.handleAfterMediaModalClose.bind(this)}
        />
      </>
    );
  }
}
