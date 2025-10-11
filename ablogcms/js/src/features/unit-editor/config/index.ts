import { UnitConfigEditorSettings } from '../components/config-editor/types';
import type { UnitEditorSettings } from '../types';

export const defaultEditorSettings: UnitEditorSettings = {
  unitGroup: {
    enable: false,
    options: [],
  },
  groupUnit: {
    classOptions: [],
    tagOptions: [],
  },
  align: {
    version: 'v2',
  },
  sizeOptions: {
    image: [],
    eximage: [],
    media: [],
    map: [],
    video: [],
    youtube: [],
  },
  unitDefs: [],
};

export const defaultConfigEditorSettings: UnitConfigEditorSettings = {
  unitGroup: {
    enable: false,
    options: [],
  },
  groupUnit: {
    classOptions: [],
    tagOptions: [],
  },
  align: {
    version: 'v2',
  },
  unitDefs: [],
  sizeOptions: {
    image: [],
    eximage: [],
    media: [],
    map: [],
    video: [],
    youtube: [],
  },
  textTagOptions: [],
};
