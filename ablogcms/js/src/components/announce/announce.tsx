interface AnnounceProps {
  title: string;
  message?: React.ReactNode;
  children?: React.ReactNode;
}

const Announce = ({ title, message = '', children }: AnnounceProps) => {
  return (
    <div className="acms-admin-announce">
      <h2 className="acms-admin-announce-title">
        <span className="acms-admin-icon acms-admin-icon-news" aria-hidden="true" />
        {title}
      </h2>
      {message && <p className="acms-admin-announce-text">{message}</p>}
      {children && <div className="acms-admin-announce-action">{children}</div>}
    </div>
  );
};

export default Announce;
