import { lazy, Suspense, useCallback, useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import useEffectOnce from '../../hooks/use-effect-once';
import { render } from '../../utils/react';
import { triggerEvent } from '../../utils';
import { MediaItem } from '../../features/media/types';

const insertMark = '.js-insert';
const updateMark = '.js-edit';
const removeMark = '.js-remove';
const previewMark = '.js-preview';
const valueMark = '.js-value';
const dropAreaMark = '.js-droparea';
const errorTextMark = '.js-text';
const initializedAttr = 'data-media-field-initialized';

const displayStates: Map<HTMLElement, string> = new Map();

function hide(element: HTMLElement): void {
  const { display } = getComputedStyle(element);

  if (display !== 'none') {
    displayStates.set(element, display);
    element.style.display = 'none';
  }
}

function show(element: HTMLElement): void {
  const { display } = getComputedStyle(element);

  if (display === 'none') {
    element.style.display = displayStates.get(element) || 'block';
  }
}

const ErrorMessage = () => (
  <span style={{ color: 'red' }}>
    &nbsp;&nbsp;機能を利用するには
    <a href={`${ACMS.Config.root}bid/${ACMS.Config.bid}/admin/config_function/`}>機能設定</a>
    にてメディア管理を利用可能にしてください
  </span>
);

export default function dispatchMediaField(context: Element | Document = document) {
  const elements = context.querySelectorAll<HTMLElement>(ACMS.Config.mediaFieldMark);
  if (ACMS.Config.mediaLibrary === 'off') {
    elements.forEach((element) => render(<ErrorMessage />, element));
    return;
  }

  const MediaDropArea = lazy(
    () =>
      import(
        /* webpackChunkName: "media-field-drop-area" */ '../../features/media/components/media-droparea/media-droparea'
      )
  );
  const MediaInsert = lazy(
    () =>
      import(/* webpackChunkName: "media-field-insert" */ '../../features/media/components/media-insert/media-insert')
  );
  const MediaUpdate = lazy(
    () =>
      import(/* webpackChunkName: "media-field-update" */ '../../features/media/components/media-update/media-update')
  );

  type DropAreaState = Pick<React.ComponentPropsWithoutRef<typeof MediaDropArea>, 'mid' | 'thumbnail' | 'mediaType'>;

  const Renderer = ({
    dropArea,
    inputs,
    removeButtons,
    previewImages,
    insertButtons,
    updateButtons,
    errorTexts,
  }: {
    dropArea: HTMLElement | null;
    inputs: NodeListOf<HTMLInputElement>;
    removeButtons: NodeListOf<HTMLButtonElement>;
    previewImages: NodeListOf<HTMLImageElement>;
    insertButtons: NodeListOf<HTMLButtonElement>;
    updateButtons: NodeListOf<HTMLButtonElement>;
    errorTexts: NodeListOf<HTMLElement>;
  }) => {
    const {
      /**
       * サムネイル画像のパス
       */
      thumbnail = '',

      /**
       * 受け付け可能なメディアタイプ
       */
      type = 'image',

      /**
       * キャプション
       */
      caption = '',

      /**
       * 幅
       */
      width = '',

      /**
       * 高さ
       */
      height = '',

      /**
       * メディアのタイプ
       */
      thumbnailType,
    } = dropArea?.dataset || {};

    const [dropAreaState, setDropAreaState] = useState<DropAreaState>({
      mid: inputs.length > 0 ? inputs[0].value : '',
      thumbnail,
      // カスタムフィールドメーカーの不具合で、thubmnailType が未指定かつ、mediaTypeがallの場合があるため、その場合はimageとして扱うことで互換性を保つ
      mediaType: thumbnailType || (type === 'all' ? 'image' : type), // thumbnailTypeが未指定の場合でも動作するようにする
    } as DropAreaState);

    useEffect(() => {
      if (dropAreaState.mid === '') {
        removeButtons.forEach((button) => {
          hide(button);
        });
        updateButtons.forEach((button) => {
          hide(button);
        });
      } else {
        removeButtons.forEach((button) => {
          show(button);
        });
        updateButtons.forEach((button) => {
          show(button);
        });
      }
    }, [dropAreaState.mid, removeButtons, updateButtons]);

    const handleChange = useCallback(
      (newMedia: MediaItem | null) => {
        if (errorTexts.length) {
          errorTexts.forEach((errorText) => {
            hide(errorText);
          });
        }
        inputs.forEach((input) => {
          input.value = newMedia?.media_id || '';
          triggerEvent(input, 'acmsAdminMediaFieldChange', { bubbles: true });
        });
        setDropAreaState(
          (prevState) =>
            ({
              ...prevState,
              mid: newMedia?.media_id || '',
              mediaType: newMedia?.media_type || '',
              thumbnail: newMedia?.media_thumbnail || '',
            }) as DropAreaState
        );
      },
      [errorTexts, inputs]
    );

    const handleError = useCallback(() => {
      if (errorTexts.length) {
        errorTexts.forEach((errorText) => {
          show(errorText);
        });
      }
    }, [errorTexts]);

    const [mode, setMode] = useState<'none' | 'insert' | 'update'>('none');

    const handleClose = useCallback(() => {
      setMode('none');
    }, []);

    const [insertModalTab, setInsertModalTab] =
      useState<React.ComponentPropsWithoutRef<typeof MediaInsert>['tab']>('select');
    const [insertModalFiletype, setInsertModalFiletype] =
      useState<React.ComponentPropsWithoutRef<typeof MediaInsert>['filetype']>(undefined);

    const handleInsert = useCallback(
      (medias: MediaItem[]) => {
        if (!medias || !medias.length) {
          alert('メディアが選択されていません。');
          return;
        }
        const [media] = medias;
        if (errorTexts.length) {
          errorTexts.forEach((errorText) => {
            hide(errorText);
          });
        }
        inputs.forEach((input) => {
          input.value = media.media_id;
          triggerEvent(input, 'acmsAdminMediaFieldChange', { bubbles: true });
        });
        if (previewImages.length > 0) {
          previewImages.forEach((preview) => {
            preview.src = media.media_thumbnail;
            show(preview);
          });
        }

        setDropAreaState(
          (prevState) =>
            ({
              ...prevState,
              mid: media.media_id,
              mediaType: media.media_type,
              thumbnail: media.media_thumbnail,
            }) as DropAreaState
        );
        setMode('none');
      },
      [errorTexts, inputs, previewImages]
    );

    useEffectOnce(() => {
      const handleInsertButtonClick = (event: MouseEvent) => {
        if (!(event.currentTarget instanceof HTMLElement)) {
          return;
        }
        const { mode: tab = '', type = 'all' } = event.currentTarget.dataset;
        setInsertModalTab(tab as React.ComponentPropsWithoutRef<typeof MediaInsert>['tab']);
        setInsertModalFiletype(type as React.ComponentPropsWithoutRef<typeof MediaInsert>['filetype']);
        setMode('insert');
      };
      insertButtons.forEach((button) => {
        button.addEventListener('click', handleInsertButtonClick);
      });
      return () => {
        insertButtons.forEach((button) => {
          button.removeEventListener('click', handleInsertButtonClick);
        });
      };
    });

    useEffectOnce(() => {
      const handleUpdateButtonClick = () => {
        setMode('update');
      };
      updateButtons.forEach((button) => {
        button.addEventListener('click', handleUpdateButtonClick);
      });
      return () => {
        updateButtons.forEach((button) => {
          button.removeEventListener('click', handleUpdateButtonClick);
        });
      };
    });

    const handleUpdate = useCallback(
      (media: MediaItem) => {
        inputs.forEach((input) => {
          input.value = media.media_id;
          triggerEvent(input, 'acmsAdminMediaFieldChange', { bubbles: true });
        });
        if (previewImages.length > 0) {
          previewImages.forEach((preview) => {
            preview.src = media.media_thumbnail;
          });
        }
        setMode('none');
      },
      [inputs, previewImages]
    );

    useEffectOnce(() => {
      const handleRemoveButtonClick = () => {
        inputs.forEach((input) => {
          input.value = '';
          triggerEvent(input, 'acmsAdminMediaFieldChange', { bubbles: true });
        });
        previewImages.forEach((preview) => {
          preview.src = '';
        });
        setDropAreaState({
          mid: '',
          thumbnail: '',
          mediaType: undefined,
        } as DropAreaState);
      };
      removeButtons.forEach((button) => {
        button.addEventListener('click', handleRemoveButtonClick);
      });

      return () => {
        removeButtons.forEach((button) => {
          button.removeEventListener('click', handleRemoveButtonClick);
        });
      };
    });

    useEffectOnce(() => {
      const listener = (event: Event) => {
        if (!(event instanceof CustomEvent)) return;
        const media = event.detail;
        handleChange(media);
      };
      inputs.forEach((input) => {
        input?.addEventListener('acms.set-main-image', listener);
      });
      return () => {
        inputs.forEach((input) => {
          input?.removeEventListener('acms.set-main-image', listener);
        });
      };
    });

    return (
      <>
        {dropArea !== null &&
          createPortal(
            <MediaDropArea
              {...dropAreaState}
              accept={type as React.ComponentPropsWithoutRef<typeof MediaDropArea>['accept']}
              caption={caption}
              width={width}
              height={height}
              onChange={handleChange}
              onError={handleError}
            />,
            dropArea
          )}
        <Suspense fallback={null}>
          <MediaInsert
            isOpen={mode === 'insert'}
            tab={insertModalTab}
            radioMode
            filetype={insertModalFiletype}
            onInsert={handleInsert}
            onClose={handleClose}
          />
        </Suspense>
        <Suspense fallback={null}>
          <MediaUpdate
            isOpen={mode === 'update'}
            mid={dropAreaState.mid}
            onClose={handleClose}
            onUpdate={handleUpdate}
          />
        </Suspense>
      </>
    );
  };
  elements.forEach((element) => {
    if (element.getAttribute(initializedAttr) === 'true') {
      return;
    }
    if (element.closest(ACMS.Config.fieldgroupSortableItemTemplateMark)) {
      return;
    }
    if (element.querySelector<HTMLElement>(ACMS.Config.mediaFieldMark) !== null) {
      // 子要素にも同じマークがある場合
      // Ver. 3.1.22時点のsite/admin/entry/ccd/realestate.htmlのテンプレートで mediaFieldMark の指定方法が間違っている問題対策
      return;
    }
    const dropArea = element.querySelector<HTMLElement>(dropAreaMark);
    const inputs = element.querySelectorAll<HTMLInputElement>(valueMark);

    if (inputs.length === 0) {
      throw new Error('Input element not found');
    }
    element.setAttribute(initializedAttr, 'true');
    const removeButtons = element.querySelectorAll<HTMLButtonElement>(removeMark);
    const previewImages = element.querySelectorAll<HTMLImageElement>(previewMark);
    const insertButtons = element.querySelectorAll<HTMLButtonElement>(insertMark);
    const updateButtons = element.querySelectorAll<HTMLButtonElement>(updateMark);
    const errorTexts = element.querySelectorAll<HTMLElement>(errorTextMark);

    const fragment = document.createDocumentFragment();

    render(
      <Suspense fallback={null}>
        <Renderer
          dropArea={dropArea}
          inputs={inputs}
          removeButtons={removeButtons}
          previewImages={previewImages}
          insertButtons={insertButtons}
          updateButtons={updateButtons}
          errorTexts={errorTexts}
        />
      </Suspense>,
      fragment
    );
  });
}
