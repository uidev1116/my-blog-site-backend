import Splash from '../../components/splash/splash';
import { usePendingStore } from './store';

const PendingTypeToComponent = {
  splash: Splash,
};

const PendingContainer = () => {
  const { snapshot } = usePendingStore();

  const Component = snapshot ? PendingTypeToComponent[snapshot.type] : null;

  return <div id="acms-pending-container">{snapshot && Component && <Component message={snapshot.message} />}</div>;
};

export default PendingContainer;
