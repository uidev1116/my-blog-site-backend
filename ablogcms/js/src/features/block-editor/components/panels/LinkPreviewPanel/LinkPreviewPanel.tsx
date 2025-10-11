import { Icon } from '../../ui/Icon';
import { Toolbar } from '../../ui/Toolbar';

export type LinkPreviewPanelProps = {
  url: string;
  onEdit: () => void;
  onClear: () => void;
};

export const LinkPreviewPanel = ({ onClear, onEdit, url }: LinkPreviewPanelProps) => (
  <div className="acms-admin-block-editor-link-preview">
    <a href={url} target="_blank" rel="noopener noreferrer" className="acms-admin-block-editor-link-preview-url">
      {url}
    </a>
    <Toolbar.Divider />
    <Toolbar.Button type="button" onClick={onEdit} aria-label="リンクを編集">
      <Icon name="edit" />
    </Toolbar.Button>
    <Toolbar.Button type="button" onClick={onClear} aria-label="リンクを削除">
      <Icon name="delete" />
    </Toolbar.Button>
  </div>
);
