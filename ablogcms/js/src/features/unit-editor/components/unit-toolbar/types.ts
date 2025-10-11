export interface UnitToolbarActionProps {
  className?: string;
  disabled?: boolean;
  style?: React.CSSProperties;
}

export interface UnitToolbarActionMenuProps {
  className?: string;
  style?: React.CSSProperties;
}

export interface UnitToolbarFeatures {
  insert: boolean;
  collapse: boolean;
  status: boolean;
  duplicate: boolean;
  delete: boolean;
  move: boolean;
  drag: boolean;
  align: boolean;
  group: boolean;
  anker: boolean;
  meta: boolean;
  wrap: boolean;
  unwrap: boolean;
}
