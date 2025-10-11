import Leaflet from 'leaflet';
import { OpenStreetMapProvider } from 'leaflet-geosearch';

export interface OpenStreetMapPickerOptions {
  searchInput: string;
  searchBtn: string;
  lngInput: string;
  latInput: string;
  zoomInput: string;
  msgInput: string;
  map: string;
  tile: string;
  onChange?: (values: UpdatePinValues) => void;
}

export interface UpdatePinOptions {
  disableViewUpdate?: boolean;
}

export interface UpdatePinValues {
  lat: number;
  lng: number;
  zoom: number;
  msg: string;
}

// setup
const provider = new OpenStreetMapProvider();

const defaultOptions: OpenStreetMapPickerOptions = {
  searchInput: '.js-search',
  searchBtn: '.js-search-btn',
  lngInput: '.js-lng',
  latInput: '.js-lat',
  zoomInput: '.js-zoom',
  msgInput: '.js-msg',
  map: '.js-map',
  tile: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
};

export default class OpenStreetMapPicker {
  private options!: OpenStreetMapPickerOptions;

  private map!: Leaflet.Map;

  private marker!: Leaflet.Marker;

  private lat: number = 0;

  private lng: number = 0;

  private zoom: number = 0;

  private msg: string = '';

  private msgEle: HTMLInputElement | null = null;

  private latEle: HTMLInputElement | null = null;

  private lngEle: HTMLInputElement | null = null;

  private zoomEle: HTMLInputElement | null = null;

  private searchInputEle: HTMLInputElement | null = null;

  private searchBtn: HTMLButtonElement | null = null;

  private parentForm: HTMLFormElement | null = null;

  private bindPopupFlag: boolean = false;

  private latinputListener!: (e: Event) => void;

  private latchangeListener!: (e: Event) => void;

  private lnginputListener!: (e: Event) => void;

  private lngchangeListener!: (e: Event) => void;

  private zoominputListener!: (e: Event) => void;

  private zoomchangeListener!: (e: Event) => void;

  private msginputListener!: (e: Event) => void;

  private formListener!: (e: KeyboardEvent) => void;

  private searchBtnListener!: () => void;

  constructor(elementOrSelector: string | HTMLElement, options?: Partial<OpenStreetMapPickerOptions>) {
    const opt = { ...defaultOptions, ...options };
    const element =
      typeof elementOrSelector === 'string'
        ? (document.querySelector(elementOrSelector) as HTMLElement | null)
        : elementOrSelector;
    if (!element) {
      throw new Error('Element not found');
    }
    const mapEle = element.querySelector<HTMLElement>(opt.map);
    if (!mapEle) {
      throw new Error('Map element not found');
    }
    const lngEle = element.querySelector<HTMLInputElement>(opt.lngInput);
    const latEle = element.querySelector<HTMLInputElement>(opt.latInput);
    const zoomEle = element.querySelector<HTMLInputElement>(opt.zoomInput);
    const searchInputEle = element.querySelector<HTMLInputElement>(opt.searchInput);
    const searchBtn = element.querySelector<HTMLButtonElement>(opt.searchBtn);
    const msgEle = element.querySelector<HTMLInputElement>(opt.msgInput);
    const lat = latEle ? Number(latEle.value) || 0 : 0;
    const lng = lngEle ? Number(lngEle.value) || 0 : 0;
    const zoom = zoomEle ? Number(zoomEle.value) || 0 : 0;
    const msg = msgEle ? msgEle.value : '';
    const map = Leaflet.map(mapEle).setView([lat, lng], zoom);
    const marker = Leaflet.marker(map.getCenter(), {
      draggable: true,
    });
    if (searchInputEle) {
      this.parentForm = searchInputEle.closest('form');
    }
    this.options = opt;
    this.map = map;
    this.marker = marker;
    this.lat = lat;
    this.lng = lng;
    this.zoom = zoom;
    this.msg = msg;
    this.msgEle = msgEle;
    this.latEle = latEle;
    this.lngEle = lngEle;
    this.zoomEle = zoomEle;
    this.searchInputEle = searchInputEle;
    this.searchBtn = searchBtn;
    this.bindPopupFlag = false;
  }

  getMap(): Leaflet.Map {
    return this.map;
  }

  setValues(): OpenStreetMapPicker {
    const { latEle, lngEle, zoomEle, msgEle } = this;
    this.lat = latEle ? Number(latEle.value) || 0 : 0;
    this.lng = lngEle ? Number(lngEle.value) || 0 : 0;
    this.zoom = zoomEle ? Number(zoomEle.value) || 0 : 0;
    this.msg = msgEle ? msgEle.value : '';
    return this;
  }

  invalidateSize(): OpenStreetMapPicker {
    this.map.invalidateSize();
    return this;
  }

  run(): OpenStreetMapPicker {
    const { map, msg, marker } = this;
    Leaflet.tileLayer(this.options.tile, {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const view = marker.addTo(map);
    if (msg) {
      view.bindPopup(msg);
    }
    this.setEvent();
    return this;
  }

  setEvent(): void {
    const { map, msgEle, marker, latEle, lngEle, zoomEle, searchBtn, searchInputEle, parentForm } = this;

    if (lngEle) {
      ['input', 'change'].forEach((eventName) => {
        const listenerName = `lng${eventName}Listener` as keyof OpenStreetMapPicker;
        const listener = (e: Event) => {
          const lng = Number((e.target as HTMLInputElement).value) || 0;
          this.updatePin({ lng });
        };
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (this[listenerName] as any) = listener;
        lngEle.addEventListener(eventName, listener);
      });
    }

    if (latEle) {
      ['input', 'change'].forEach((eventName) => {
        const listenerName = `lat${eventName}Listener` as keyof OpenStreetMapPicker;
        const listener = (e: Event) => {
          const lat = Number((e.target as HTMLInputElement).value) || 0;
          this.updatePin({ lat });
        };
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (this[listenerName] as any) = listener;
        latEle.addEventListener(eventName, listener);
      });
    }

    if (zoomEle) {
      ['input', 'change'].forEach((eventName) => {
        const listenerName = `zoom${eventName}Listener` as keyof OpenStreetMapPicker;
        const listener = (e: Event) => {
          const zoom = Number((e.target as HTMLInputElement).value) || 0;
          this.updatePin({ zoom });
        };
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (this[listenerName] as any) = listener;
        zoomEle.addEventListener(eventName, listener);
      });
    }

    if (msgEle) {
      ['input', 'change'].forEach((eventName) => {
        const listenerName = `msg${eventName}Listener` as keyof OpenStreetMapPicker;
        const listener = (e: Event) => {
          const msg = (e.target as HTMLInputElement).value;
          this.updatePin({ msg });
        };
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (this[listenerName] as any) = listener;
        msgEle.addEventListener('input', listener);
      });
    }

    if (parentForm) {
      parentForm.addEventListener(
        'keypress',
        (this.formListener = (e: KeyboardEvent) => {
          if (e.target === searchInputEle && e.keyCode === 13) {
            if (searchBtn) {
              searchBtn.click();
            }
            e.preventDefault();
            return false;
          }
        })
      );
    }

    if (searchBtn && searchInputEle) {
      searchBtn.addEventListener(
        'click',
        (this.searchBtnListener = () => {
          const query = searchInputEle.value;
          provider.search({ query }).then((results) => {
            if (results.length) {
              const result = results[0];
              if (latEle && lngEle) {
                latEle.value = result.x.toString();
                lngEle.value = result.y.toString();
              }
              this.updatePin({
                lng: result.x,
                lat: result.y,
              });
            }
          });
        })
      );
    }

    map.on('zoomend', () => {
      this.updatePin({ zoom: map.getZoom() }, { disableViewUpdate: true });
    });

    marker.on('drag', () => {
      const position = marker.getLatLng();
      this.updatePin({ lat: position.lat, lng: position.lng }, { disableViewUpdate: true });
    });

    marker.on('dragend', () => {
      const position = marker.getLatLng();
      map.panTo(new Leaflet.LatLng(position.lat, position.lng));
    });
  }

  updatePin(values: Partial<UpdatePinValues>, options: UpdatePinOptions = {}): OpenStreetMapPicker {
    const { disableViewUpdate = false } = options;
    const { lat = this.lat, lng = this.lng, zoom = this.zoom, msg = this.msg } = values;

    if (!disableViewUpdate) {
      if (lat !== this.lat || lng !== this.lng || zoom !== this.zoom) {
        this.map.setView([lat, lng], zoom);
      }
    }
    this.marker.setLatLng([lat, lng]);
    this.lat = lat;
    this.lng = lng;
    this.zoom = zoom;
    this.msg = msg;
    if (this.latEle) this.latEle.value = lat.toString();
    if (this.lngEle) this.lngEle.value = lng.toString();
    if (this.zoomEle) this.zoomEle.value = zoom.toString();
    if (msg) {
      if (this.bindPopupFlag) {
        this.marker.bindPopup(msg);
        this.bindPopupFlag = false;
      }
      this.marker.setPopupContent(msg);
    } else {
      this.marker.closePopup();
      this.marker.unbindPopup();
      this.bindPopupFlag = true;
    }

    if (this.options.onChange) {
      this.options.onChange({
        lat: this.lat,
        lng: this.lng,
        zoom: this.zoom,
        msg: this.msg,
      });
    }

    return this;
  }

  destroy(): void {
    const { latEle, lngEle, zoomEle, msgEle, searchBtn, parentForm } = this;
    if (latEle) {
      latEle.removeEventListener('input', this.latinputListener);
      latEle.removeEventListener('change', this.latchangeListener);
    }

    if (lngEle) {
      lngEle.removeEventListener('input', this.lnginputListener);
      lngEle.removeEventListener('change', this.lngchangeListener);
    }

    if (zoomEle) {
      zoomEle.removeEventListener('input', this.zoominputListener);
      zoomEle.removeEventListener('change', this.zoomchangeListener);
    }

    if (msgEle) {
      msgEle.removeEventListener('input', this.msginputListener);
      msgEle.removeEventListener('change', this.msginputListener);
    }

    if (searchBtn) {
      searchBtn.removeEventListener('click', this.searchBtnListener);
    }

    if (parentForm) {
      parentForm.removeEventListener('keypress', this.formListener);
    }

    this.map.remove();
    this.map.off();
  }
}
