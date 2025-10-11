export type PendingType = 'splash';

export interface PendingProps extends React.HTMLAttributes<HTMLDivElement> {
  message: string;
}

export interface PendingStore {
  type: PendingType;
  message: string;
}
