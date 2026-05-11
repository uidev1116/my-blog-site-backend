import { Component } from 'react';
import { fetchClient } from '../../../../lib/fetch-client';
import MediaModal from '../media-modal/media-modal';
import type { MediaStateProps } from '../../types';
import { notify } from '../../../../lib/notify';

interface MediaUpdateModalProps
  extends MediaStateProps,
    Pick<React.ComponentProps<typeof MediaModal>, 'onClose' | 'isOpen' | 'onUpdate' | 'container'> {
  mid: number;
}

export default class MediaUpdateModal extends Component<MediaUpdateModalProps> {
  handleAfterMediaModalOpen() {
    const { mid } = this.props;
    const url = ACMS.Library.acmsLink(
      {
        tpl: 'ajax/edit/media-edit.json',
        bid: ACMS.Config.bid,
        Query: {
          cache: new Date().getTime().toString(),
        },
      },
      false
    );
    fetchClient
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      .get<any>(url, {
        params: {
          _mid: mid.toString(),
        },
      })
      .then((res) => {
        if (res.data.status === 'failure') {
          notify.danger(ACMS.i18n('media.get_media_error'));
          return;
        }
        this.props.actions.setItem(res.data.item);
      })
      .catch(() => {
        notify.danger(ACMS.i18n('media.get_media_error'));
      });
    const tagUrl = ACMS.Library.acmsLink(
      {
        tpl: 'ajax/edit/media-tag.json',
        bid: ACMS.Config.bid,
        Query: {
          cache: new Date().getTime().toString(),
        },
      },
      false
    );
    fetchClient.get<string[]>(tagUrl).then((res) => {
      this.props.actions.setMediaTags(res.data);
    });
  }

  handleAfterMediaModalClose() {
    this.props.actions.setItem(null);
  }

  render() {
    const { actions, item, formToken, tags, onClose, onUpdate, isOpen, container } = this.props;

    return (
      <MediaModal
        isOpen={isOpen}
        item={item}
        actions={actions}
        formToken={formToken}
        tags={tags}
        onUpdate={onUpdate}
        onClose={onClose}
        onAfterClose={this.handleAfterMediaModalClose.bind(this)}
        onAfterOpen={this.handleAfterMediaModalOpen.bind(this)}
        container={container}
      />
    );
  }
}
