import usePopover from './use-popover';

// コンテキストで共有するデータ
// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface PopoverContextType extends ReturnType<typeof usePopover> {}

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface PopoverRef
  extends Pick<ReturnType<typeof usePopover>, 'openPopover' | 'closePopover' | 'togglePopover'> {}
