import { useState, useCallback } from 'react';
import { ButtonV2 } from '@components/button-v2';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { isValidUrl } from '../../../../../utils';
// import { Toggle } from '@features/block-editor/components/ui/Toggle';

export type LinkEditorPanelProps = {
  initialUrl?: string;
  initialOpenInNewTab?: boolean;
  onSetLink: (url: string, openInNewTab?: boolean) => void;
  onClear?: () => void;
};

export const useLinkEditorState = ({ initialUrl, initialOpenInNewTab, onSetLink }: LinkEditorPanelProps) => {
  const [url, setUrl] = useState(initialUrl || '');
  const [openInNewTab, setOpenInNewTab] = useState(initialOpenInNewTab || false);

  const onChange = useCallback((event: React.ChangeEvent<HTMLInputElement>) => {
    setUrl(event.target.value);
  }, []);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      if (isValidUrl(url)) {
        onSetLink(url, openInNewTab);
      } else {
        alert('有効なURLを入力してください。'); // エラーメッセージの表示
      }
    },
    [url, openInNewTab, onSetLink]
  );

  return {
    url,
    setUrl,
    openInNewTab,
    setOpenInNewTab,
    onChange,
    handleSubmit,
    isValidUrl,
  };
};

export const LinkEditorPanel = ({ onSetLink, onClear, initialOpenInNewTab, initialUrl }: LinkEditorPanelProps) => {
  const state = useLinkEditorState({ onSetLink, initialOpenInNewTab, initialUrl });

  const handleClear = useCallback(() => {
    onClear?.();
    state.setUrl('');
    state.setOpenInNewTab(false);
  }, [onClear, state]);

  return (
    <div className="acms-admin-form acms-admin-block-editor-link-editor acms-admin-block-editor-popover-form">
      <form onSubmit={state.handleSubmit}>
        {/* eslint-disable-next-line jsx-a11y/label-has-associated-control */}
        <div className="acms-admin-block-editor-popover-form-item">
          <label className="acms-admin-form-block">
            <span className="acms-admin-block-editor-popover-form-item-label">リンク先URL</span>
            <input
              type="text"
              className="acms-admin-form-width-full"
              placeholder="URLを入力"
              value={state.url}
              onChange={state.onChange}
            />
          </label>
        </div>
        <div className="acms-admin-block-editor-popover-form-item">
          <div className="acms-admin-form-checkbox">
            <input
              type="checkbox"
              id="openInNewTab"
              value="true"
              checked={state.openInNewTab}
              onChange={(prev) => {
                state.setOpenInNewTab(prev.target.checked);
              }}
            />
            <label htmlFor="openInNewTab">
              <i className="acms-admin-ico-checkbox" />
              リンク先を別タブで開く
            </label>
          </div>
        </div>

        <div className="acms-admin-block-editor-popover-form-button-group">
          {initialUrl ? (
            <>
              <ButtonV2 variant="outlined" size="small" type="submit">
                <Icon name="sync" />
                変更
              </ButtonV2>
              <ButtonV2 variant="filled" size="small" type="button" onClick={handleClear}>
                <Icon name="delete" />
                解除
              </ButtonV2>
            </>
          ) : (
            <ButtonV2 variant="filled" size="small" type="submit">
              追加
            </ButtonV2>
          )}
        </div>
      </form>
    </div>
  );
};
