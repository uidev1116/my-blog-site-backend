import loadGoogleMap from './loader';

export default async (element: HTMLElement) => {
  const lat = parseFloat(element.getAttribute('data-lat') ?? '0');
  const lng = parseFloat(element.getAttribute('data-lng') ?? '0');
  let pitch = parseFloat(element.getAttribute('data-pitch') ?? '0');
  let heading = parseFloat(element.getAttribute('data-heading') ?? '0');
  let zoom = parseFloat(element.getAttribute('data-zoom') ?? '0');
  pitch = Number.isNaN(pitch) ? 0 : pitch;
  heading = Number.isNaN(heading) ? 0 : heading;
  zoom = Number.isNaN(zoom) ? 0 : zoom;

  const google = await loadGoogleMap(ACMS.Config.googleApiKey);

  // eslint-disable-next-line no-new
  new google.maps.StreetViewPanorama(element, {
    position: { lat, lng },
    pov: { heading, pitch },
    zoom,
  });
};
