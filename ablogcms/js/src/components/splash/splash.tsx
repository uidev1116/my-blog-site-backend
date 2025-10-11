import Spinner from '@components/spinner/spinner';

const Splash = ({ message = ACMS.i18n('splash.default') }) => (
  <div className="acms-admin-splash" role="status">
    <div className="acms-admin-splash-frame">
      <div className="acms-admin-splash-spinner">
        <Spinner size={32} />
      </div>
      <span className="acms-admin-splash-message">{message}</span>
    </div>
  </div>
);

export default Splash;
