import { useState, useCallback, useRef } from 'react';
import html2canvas from 'html2canvas-pro';
import { saveAs } from 'file-saver';
import type { Options as Html2CanvasOptions } from 'html2canvas-pro';

export default function useCapture() {
  const iframeRef = useRef<HTMLIFrameElement | null>(null);
  const [isCapturing, setIsCapturing] = useState(false);

  const setIframeRef = useCallback((iframe: HTMLIFrameElement | null) => {
    iframeRef.current = iframe;
  }, []);

  const doCaptureHtml = useCallback(async (element: HTMLElement, options: Partial<Html2CanvasOptions>) => {
    const canvas = await html2canvas(element, options);
    return new Promise<void>((resolve) => {
      canvas.toBlob((blob) => {
        if (!blob) {
          resolve();
          return;
        }
        saveAs(blob, `${(window.location.host + window.location.pathname).replace(/(\/|\.)/g, '_')}.png`);
        resolve();
      });
    });
  }, []);

  const capture = useCallback(async () => {
    if (!iframeRef.current) {
      return;
    }
    const iframeBody = iframeRef.current.contentDocument?.querySelector('body');
    if (!iframeBody) {
      return;
    }
    setIsCapturing(true);
    await doCaptureHtml(iframeBody, {
      logging: false,
      width: iframeRef.current.offsetWidth,
    });
    setIsCapturing(false);
  }, [doCaptureHtml, iframeRef]);

  return { setIframeRef, isCapturing, capture };
}
