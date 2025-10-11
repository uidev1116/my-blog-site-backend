export interface TiptapProps {
  defaultValue: string;
  onChange: (value: string) => void;
}

export type EditorUser = {
  clientId: string;
  name: string;
  color: string;
  initials?: string;
};
