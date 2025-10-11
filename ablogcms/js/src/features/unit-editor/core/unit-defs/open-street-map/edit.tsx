import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import { useCallback } from 'react';
import VStack from '@components/stack/v-stack';
import { useSettings } from '@features/unit-editor/stores/settings';
import OpenStreetMapView from './components/open-street-map-view';
import OpenStreetMapSearch from './components/open-street-map-search';
import type { OpenStreetMapAttributes } from './types';

interface OpenStreetMapUnitContentProps {
  unit: UnitEditProps<OpenStreetMapAttributes>['unit'];
  editor: UnitEditProps<OpenStreetMapAttributes>['editor'];
}

export const OpenStreetMapUnitContent = ({ editor, unit }: OpenStreetMapUnitContentProps) => {
  const { sizeOptions } = useSettings();
  const handleMarkerChange = useCallback(
    (value: { lat: number; lng: number }) => {
      editor.commands.setUnitAttributes(unit.id, {
        ...unit.attributes,
        ...value,
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

  return (
    <UnitContent unit={unit}>
      <table className="formColumnMap">
        <tbody>
          <tr>
            <td className="formColumnMapTd">
              <VStack align="stretch">
                <OpenStreetMapSearch onSearch={handleSearch} />
                <OpenStreetMapView
                  lat={unit.attributes.lat}
                  lng={unit.attributes.lng}
                  zoom={unit.attributes.zoom}
                  msg={unit.attributes.msg}
                  onMarkerChange={handleMarkerChange}
                  onZoomChange={handleZoomChange}
                />
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
                          name={`map_size_${unit.id}`}
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
                </tbody>
              </table>
              <table className="entryFormImageTable acms-admin-mt-1">
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
                            name={`map_lat_${unit.id}`}
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
                            name={`map_lng_${unit.id}`}
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
                            type="number"
                            name={`map_zoom_${unit.id}`}
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
                            name={`map_msg_${unit.id}`}
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
            </td>
          </tr>
        </tbody>
      </table>
    </UnitContent>
  );
};
const Edit = ({ editor, unit, handleProps }: UnitEditProps<OpenStreetMapAttributes>) => {
  return (
    <div>
      <div>
        <CommonUnitToolbar editor={editor} unit={unit} handleProps={handleProps} />
      </div>
      <div>
        <OpenStreetMapUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
