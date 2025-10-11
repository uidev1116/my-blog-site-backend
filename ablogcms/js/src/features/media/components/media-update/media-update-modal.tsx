import { Component } from 'react';
import axiosLib from '../../../../lib/axios';
import MediaModal from '../media-modal/media-modal';
import type { MediaStateProps } from '../../types';

interface MediaUpdateModalProps
  extends MediaStateProps,
    Pick<React.ComponentProps<typeof MediaModal>, 'onClose' | 'isOpen' | 'onUpdate' | 'container'> {
  mid: string;
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
    axiosLib
      .get(url, {
        params: {
          _mid: mid,
        },
      })
      .then((res) => {
        this.props.actions.setItem(res.data.item);
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
    axiosLib.get(tagUrl).then((res) => {
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
