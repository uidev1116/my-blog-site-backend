import styled from 'styled-components';
import { v4 as uuid } from 'uuid';
import { findChildren } from 'smartblock/pm/utils';
import { Button, Dispatch, findSelectedNodeWithType } from 'smartblock';
import { setBlockType } from 'smartblock/pm/commands';

import { EditorState } from 'smartblock/pm/state';
import { Node } from 'smartblock/pm/model';
import { useCallback, useEffect, useState } from 'react';
import { MediaItem } from '@features/media/types';
import CenterIcon from '../icons/center';
import FullIcon from '../icons/full';
import EditIcon from '../icons/edit';
import MediaUpdate from '../../../../media/components/media-update/media-update';
import DropArea from './droparea';

const MediaMenu = styled.div`
  position: absolute;
`;

const MediaMenuTool = styled.div`
  width: 100%;
  padding: 20px 20px 0;
  white-space: nowrap;
  background-color: transparent;
  border-bottom-right-radius: 3px;
  border-bottom-left-radius: 3px;
`;

const BtnGroup = styled.div`
  display: inline-table;
  margin-right: 5px;
  vertical-align: middle;
`;

interface LayoutProps {
  state: EditorState;
  dispatch: Dispatch;
  dom: HTMLElement;
}

const Layout = ({ state, dispatch, dom }: LayoutProps) => {
  const { offsetHeight } = dom;
  const [height, setHeight] = useState(offsetHeight);

  useEffect(() => {
    const interval = setInterval(() => {
      if (dom.offsetHeight !== height) {
        setHeight(height);
      }
    }, 100);
    return () => {
      clearInterval(interval);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const style = {
    height: `${height}px`,
    left: '0',
    right: '0',
    top: '-130px',
  };

  const node = findSelectedNodeWithType(state.schema.nodes.media, state);
  const [modalOpen, setModalOpen] = useState(false);

  const handleMediaUpdate = useCallback(
    (media: MediaItem) => {
      setModalOpen(false);
      const attr = {
        ...node.attrs,
        media_id: media.media_id,
        src: media.media_edited,
      };
      setBlockType(state.schema.nodes.media, attr)(state, dispatch);
    },
    [node?.attrs, state, dispatch]
  );

  return (
    <>
      {node && node.attrs.media_id && (
        <MediaMenuTool style={{ position: 'absolute', top: '-70px', left: '10px' }}>
          <BtnGroup>
            <Button
              type="button"
              style={{
                marginRight: '1px',
                borderTopRightRadius: '0',
                borderBottomRightRadius: '0',
                opacity: node.attrs.size !== 'small' ? '.6' : '1',
              }}
              onClick={() => {
                const attr = { ...node.attrs, size: 'full' };
                setBlockType(state.schema.nodes.media, attr)(state, dispatch);
              }}
            >
              <FullIcon style={{ width: '20px', height: '20px', color: '#333' }} />
            </Button>
            <Button
              type="button"
              style={{
                borderTopLeftRadius: '0',
                borderBottomLeftRadius: '0',
                opacity: node.attrs.size === 'small' ? '.6' : '1',
              }}
              onClick={() => {
                const attr = { ...node.attrs, size: 'small' };
                setBlockType(state.schema.nodes.media, attr)(state, dispatch);
              }}
            >
              <CenterIcon style={{ width: '20px', height: '20px', color: '#333' }} />
            </Button>
          </BtnGroup>
          <Button
            type="button"
            style={{
              background: '#333333',
              padding: '2px 5px 8px 5px',
              verticalAlign: 'middle',
              width: 'auto',
            }}
            onClick={() => {
              setModalOpen(true);
            }}
          >
            <EditIcon
              style={{
                width: '16px',
                height: '16px',
                color: '#FFF',
                verticalAlign: 'middle',
                display: 'inline-block',
              }}
            />
            <span
              style={{
                fontSize: '12px',
                display: 'inline-block',
                marginLeft: '3px',
                color: '#FFF',
              }}
            >
              編集
            </span>
          </Button>
        </MediaMenuTool>
      )}
      {(!node || !node.attrs.media_id) && (
        <MediaMenu style={style}>
          <DropArea
            mediaType="image"
            onChange={(items) => {
              const nodes = items
                .map((item) => {
                  // eslint-disable-next-line camelcase
                  const { media_edited: src, media_id } = item;
                  return state.schema.nodes.media.createAndFill({
                    src,
                    media_id, // eslint-disable-line camelcase
                    id: uuid(),
                  });
                })
                .filter((node) => node !== null) as Node[];
              const { selection } = state;
              const { $anchor } = selection;
              // eslint-disable-next-line @typescript-eslint/no-explicit-any
              const resolvedPos = state.doc.resolve($anchor.pos) as any;
              const rowNumber = resolvedPos.path[1];
              let i = 0;
              const [firstNode] = findChildren(
                state.doc,
                () => {
                  if (rowNumber === i) {
                    i++;
                    return true;
                  }
                  i++;
                  return false;
                },
                false
              );
              const firstIndex = firstNode.pos;
              const removeTransaction = state.tr.delete(firstIndex, firstIndex + firstNode.node.content.size + 2);
              dispatch(removeTransaction.insert(firstIndex, nodes));
            }}
          />
        </MediaMenu>
      )}
      {parseInt(node?.attrs.media_id, 10) > 0 && (
        <MediaUpdate
          isOpen={modalOpen}
          mid={parseInt(node.attrs.media_id, 10)}
          onClose={() => {
            setModalOpen(false);
          }}
          onUpdate={handleMediaUpdate}
        />
      )}
    </>
  );
};

export default Layout;
