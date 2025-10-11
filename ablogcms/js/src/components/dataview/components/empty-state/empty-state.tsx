import Announce from '../../../announce/announce';

interface EmptyStateProps {
  title?: string;
  message?: React.ReactNode;
  children?: React.ReactNode;
}

const EmptyState = ({ title = ACMS.i18n('dataview.empty_state_title'), message = '', children }: EmptyStateProps) => {
  return (
    <Announce title={title} message={message}>
      {children}
    </Announce>
  );
};

export default EmptyState;
