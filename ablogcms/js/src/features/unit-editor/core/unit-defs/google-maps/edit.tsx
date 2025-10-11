import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import { useCallback, useRef, useState } from 'react';
import VStack from '@components/stack/v-stack';
import { useSettings } from '@features/unit-editor/stores/settings';
import Alert from '@components/alert/alert';
import { MapProvider } from './context/MapContext';
import Map from './components/Map';
import Marker from './components/Marker';
import InfoWindow from './components/InfoWindow';
import GeoSearch from './components/GeoSearch';
import type { GoogleMapsAttributes } from './types';

interface GoogleMapsUnitContentProps {
  unit: UnitEditProps<GoogleMapsAttributes>['unit'];
  editor: UnitEditProps<GoogleMapsAttributes>['editor'];
}

export const GoogleMapsUnitContent = ({ editor, unit }: GoogleMapsUnitContentProps) => {
  const mapRef = useRef<google.maps.Map | null>(null);
  const panoramaRef = useRef<google.maps.StreetViewPanorama | null>(null);
  const handleMapInit = useCallback(
    (map: google.maps.Map) => {
      mapRef.current = map;
      const panorama = map.getStreetView();
      panorama.setPosition({ lat: unit.attributes.lat, lng: unit.attributes.lng });
      panorama.setPov(
        /** @type {google.maps.StreetViewPov} */ {
          heading: unit.attributes.view_heading,
          pitch: unit.attributes.view_pitch,
        }
      );
      panorama.setZoom(unit.attributes.view_zoom);
      panorama.addListener('pov_changed', () => {
        editor.commands.setUnitAttributes(unit.id, {
          view_heading: panorama.getPov().heading,
          view_pitch: panorama.getPov().pitch,
          view_zoom: panorama.getZoom(),
        });
      });
      panorama.addListener('position_changed', () => {
        editor.commands.setUnitAttributes(unit.id, {
          lat: panorama.getPosition().lat(),
          lng: panorama.getPosition().lng(),
        });
      });
      if (unit.attributes.view_activate) {
        panorama.setVisible(true);
      }
      panoramaRef.current = panorama;
    },
    [editor.commands, unit.id, unit.attributes]
  );
  const { sizeOptions } = useSettings();
  const [isInfoWindowOpen, setIsInfoWindowOpen] = useState(false);

  const handleMarkerClick = useCallback(() => {
    setIsInfoWindowOpen(true);
  }, []);

  const handleInfoWindowClose = useCallback(() => {
    setIsInfoWindowOpen(false);
  }, []);

  const handleMarkerChange = useCallback(
    (event: google.maps.MouseEvent) => {
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        lat: event.latLng.lat(),
        lng: event.latLng.lng(),
      });
    },
    [editor, unit.id, unit.attributes]
  );

  const handleZoomChange = useCallback(
    (zoom: number) => {
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        zoom,
      });
    },
    [editor, unit.id, unit.attributes]
  );

  const handleMapSizeChange = useCallback(
    (event: React.ChangeEvent<HTMLSelectElement>) => {
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        size: event.target.value,
      });
    },
    [editor, unit.id, unit.attributes]
  );

  const handleSearch = useCallback(
    (value: { lat: number; lng: number }) => {
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        ...value,
      });
      if (panoramaRef.current && panoramaRef.current.getVisible()) {
        panoramaRef.current.setPosition(value);
      }
    },
    [editor, unit.id, unit.attributes]
  );

  const handleLatInputChange = useCallback(
    (event: React.ChangeEvent<HTMLInputElement>) => {
      const lat = Number(event.target.value) || 0;
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        lat,
      });
    },
    [editor, unit.id, unit.attributes]
  );

  const handleLngInputChange = useCallback(
    (event: React.ChangeEvent<HTMLInputElement>) => {
      const lng = Number(event.target.value) || 0;
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        lng,
      });
    },
    [editor, unit.id, unit.attributes]
  );

  const handleZoomInputChange = useCallback(
    (event: React.ChangeEvent<HTMLInputElement>) => {
      const zoom = Number(event.target.value) || 0;
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        zoom,
      });
    },
    [editor, unit.id, unit.attributes]
  );

  const handleMsgInputChange = useCallback(
    (event: React.ChangeEvent<HTMLTextAreaElement>) => {
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        msg: event.target.value,
      });
    },
    [editor, unit.id, unit.attributes]
  );

  const handleStreetViewActivateInputChange = useCallback(
    (event: React.ChangeEvent<HTMLInputElement>) => {
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        view_activate: event.target.checked,
      });
      if (event.target.checked) {
        const { lat, lng } = unit.attributes;
        panoramaRef.current?.setPosition({ lat, lng });
        panoramaRef.current?.setVisible(true);
      } else {
        panoramaRef.current?.setVisible(false);
      }
    },
    [editor, unit.id, unit.attributes]
  );

  const handlePitchInputChange = useCallback(
    (event: React.ChangeEvent<HTMLInputElement>) => {
      const pitch = Number(event.target.value) || 0;
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        view_pitch: pitch,
      });
    },
    [editor, unit.id, unit.attributes]
  );

  const handleViewZoomInputChange = useCallback(
    (event: React.ChangeEvent<HTMLInputElement>) => {
      const zoom = Number(event.target.value) || 0;
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        view_zoom: zoom,
      });
    },
    [editor, unit.id, unit.attributes]
  );

  const handleHeadingInputChange = useCallback(
    (event: React.ChangeEvent<HTMLInputElement>) => {
      const heading = Number(event.target.value) || 0;
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        view_heading: heading,
      });
    },
    [editor, unit.id, unit.attributes]
  );

  return (
    <UnitContent unit={unit}>
      <table className="formColumnMap">
        <tbody>
          <tr>
            <td className="formColumnMapTd">
              <VStack align="stretch">
                <MapProvider apiKey={ACMS.Config.googleApiKey}>
                  <GeoSearch onSearch={handleSearch} />
                  <Map
                    center={{ lat: unit.attributes.lat, lng: unit.attributes.lng }}
                    zoom={unit.attributes.zoom}
                    onZoomChange={handleZoomChange}
                    onInit={handleMapInit}
                  >
                    <Marker
                      position={{ lat: unit.attributes.lat, lng: unit.attributes.lng }}
                      draggable
                      onDragEnd={handleMarkerChange}
                      onClick={handleMarkerClick}
                    >
                      {unit.attributes.msg && (
                        <InfoWindow
                          position={{ lat: unit.attributes.lat, lng: unit.attributes.lng }}
                          content={unit.attributes.msg}
                          isOpen={isInfoWindowOpen}
                          onClose={handleInfoWindowClose}
                        />
                      )}
                    </Marker>
                  </Map>
                </MapProvider>
              </VStack>
            </td>
            <td className="entryFormFileControl">
              <table>
                <tbody>
                  <tr>
                    <th>
                      <label htmlFor={`unit_map_size-${unit.id}`}>地図の大きさ</label>
                    </th>
                    <td>
                      <div className="acms-admin-form-group acms-admin-m-0">
                        <select
                          id={`unit_map_size-${unit.id}`}
                          value={unit.attributes.size}
                          onChange={handleMapSizeChange}
                        >
                          {sizeOptions.map.map((option) => (
                            <option key={option.value} value={option.value}>
                              {option.label}
                            </option>
                          ))}
                        </select>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <th>
                      <label htmlFor={`unit_map_view_activate_${unit.id}`}>ストリートビュー</label>
                    </th>
                    <td>
                      <div className="acms-admin-form-checkbox">
                        <input
                          type="checkbox"
                          id={`unit_map_view_activate_${unit.id}`}
                          checked={unit.attributes.view_activate}
                          onChange={handleStreetViewActivateInputChange}
                        />
                        <label htmlFor={`unit_map_view_activate_${unit.id}`}>
                          <i className="acms-admin-ico-checkbox" />
                          利用する
                        </label>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>

              {/* ストリートビュー設定 */}
              <details
                className="acms-admin-accordion"
                style={{ display: unit.attributes.view_activate ? 'block' : 'none' }}
              >
                <summary className="acms-admin-accordion-button acms-admin-text-left">
                  <span className="acms-admin-icon-arrow-small-down acms-admin-ms-2" aria-hidden="true" />
                  詳細
                </summary>
                <div className="acms-admin-accordion-panel">
                  <table className="entryFormImageTable">
                    <tbody>
                      <tr>
                        <td>
                          <dl className="acms-admin-m-0">
                            <dt>
                              <label htmlFor={`unit-map-view-pitch-${unit.id}`}>ピッチ</label>
                            </dt>
                            <dd>
                              <input
                                type="text"
                                value={unit.attributes.view_pitch}
                                onChange={handlePitchInputChange}
                                size={9}
                                id={`unit-map-view-pitch-${unit.id}`}
                                className="acms-admin-form-width-mini"
                              />
                            </dd>
                            <dt>
                              <label htmlFor={`unit-map-view-zoom-${unit.id}`}>ズーム</label>
                            </dt>
                            <dd>
                              <input
                                type="text"
                                value={unit.attributes.view_zoom}
                                onChange={handleViewZoomInputChange}
                                size={9}
                                id={`unit-map-view-zoom-${unit.id}`}
                                className="acms-admin-form-width-mini"
                              />
                            </dd>
                            <dt>
                              <label htmlFor={`unit-map-view-heading-${unit.id}`}>ヘディング</label>
                            </dt>
                            <dd>
                              <input
                                type="text"
                                value={unit.attributes.view_heading}
                                onChange={handleHeadingInputChange}
                                size={9}
                                id={`unit-map-view-heading-${unit.id}`}
                                className="acms-admin-form-width-mini"
                              />
                            </dd>
                          </dl>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </details>

              <details
                className="acms-admin-accordion"
                style={{ display: unit.attributes.view_activate ? 'none' : 'block' }}
              >
                <summary className="acms-admin-accordion-button acms-admin-text-left">
                  <span className="acms-admin-icon-arrow-small-down acms-admin-ms-2" aria-hidden="true" />
                  詳細
                </summary>
                <div className="acms-admin-accordion-panel">
                  <table className="entryFormImageTable">
                    <tbody>
                      <tr>
                        <td>
                          <dl className="acms-admin-m-0">
                            <dt>
                              <label htmlFor={`unit-map-lat-${unit.id}`}>緯度</label>
                            </dt>
                            <dd>
                              <input
                                type="text"
                                value={unit.attributes.lat}
                                onChange={handleLatInputChange}
                                size={9}
                                id={`unit-map-lat-${unit.id}`}
                                className="acms-admin-form-width-mini"
                              />
                            </dd>
                            <dt>
                              <label htmlFor={`unit-map-lng-${unit.id}`}>経度</label>
                            </dt>
                            <dd>
                              <input
                                type="text"
                                value={unit.attributes.lng}
                                onChange={handleLngInputChange}
                                size={10}
                                id={`unit-map-lng-${unit.id}`}
                                className="acms-admin-form-width-mini"
                              />
                            </dd>
                            <dt>
                              <label htmlFor={`unit-map-zoom-${unit.id}`}>ズーム</label>
                            </dt>
                            <dd>
                              <input
                                type="text"
                                value={unit.attributes.zoom}
                                onChange={handleZoomInputChange}
                                size={2}
                                id={`unit-map-zoom-${unit.id}`}
                                className="acms-admin-form-width-mini"
                              />
                            </dd>
                            <dt>
                              <label htmlFor={`unit-map-msg-${unit.id}`}>吹き出し ( HTML可 )</label>
                            </dt>
                            <dd>
                              <textarea
                                value={unit.attributes.msg}
                                onChange={handleMsgInputChange}
                                rows={9}
                                id={`unit-map-msg-${unit.id}`}
                                className="acms-admin-form-width-full"
                              />
                            </dd>
                          </dl>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </details>

              {ACMS.Config.googleApiKey === '' && (
                <Alert type="danger" icon={<i className="acms-admin-icon-news" aria-hidden="true" />}>
                  <div>
                    Google Maps をご利用するには、
                    <a href="https://console.developers.google.com" target="_blank" rel="noreferrer">
                      Google API Key
                    </a>
                    が必要になります。
                    <br />
                    Api Keyを取得して「コンフィグ &gt; プロパティ設定 &gt; Google Maps API Key」に設定ください。
                  </div>
                </Alert>
              )}
            </td>
          </tr>
        </tbody>
      </table>

      <input type="hidden" name={`map_size_${unit.id}`} value={unit.attributes.size} />
      <input
        type="hidden"
        name={`map_view_activate_${unit.id}`}
        value={unit.attributes.view_activate ? 'true' : 'false'}
      />
      <input type="hidden" name={`map_view_pitch_${unit.id}`} value={unit.attributes.view_pitch} />
      <input type="hidden" name={`map_view_zoom_${unit.id}`} value={unit.attributes.view_zoom} />
      <input type="hidden" name={`map_view_heading_${unit.id}`} value={unit.attributes.view_heading} />
      <input type="hidden" name={`map_lat_${unit.id}`} value={unit.attributes.lat} />
      <input type="hidden" name={`map_lng_${unit.id}`} value={unit.attributes.lng} />
      <input type="hidden" name={`map_zoom_${unit.id}`} value={unit.attributes.zoom} />
      <input type="hidden" name={`map_msg_${unit.id}`} value={unit.attributes.msg} />
    </UnitContent>
  );
};

const Edit = ({ editor, unit, handleProps }: UnitEditProps<GoogleMapsAttributes>) => {
  return (
    <div>
      <div>
        <CommonUnitToolbar editor={editor} unit={unit} handleProps={handleProps} />
      </div>
      <div>
        <GoogleMapsUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
