import { useState, useCallback, useEffect, useRef } from 'react';
import * as api from '../api';

export default function useShareUrl() {
  const [status, setStatus] = useState<'standby' | 'waiting' | 'done'>('standby');
  const [error, setError] = useState<string>('');
  const [shareUrl, setShareUrl] = useState('');
  const inputRef = useRef<HTMLInputElement | null>(null);

  const setInputRef = useCallback((input: HTMLInputElement | null) => {
    inputRef.current = input;
  }, []);
  const standby = useCallback(() => {
    setStatus('standby');
    setError('');
    setShareUrl('');
  }, []);

  const doCreatePreviewShareUrl = useCallback(async (url: string) => {
    const formData = new FormData();
    formData.append('uri', url);
    const shareUrl = await api.createPreviewShareUrl(formData);
    return shareUrl;
  }, []);

  const createShareUrl = useCallback(
    async (url: string) => {
      setStatus('waiting');
      try {
        const shareUrl = await doCreatePreviewShareUrl(url);
        setShareUrl(shareUrl);
        setStatus('done');
      } catch (error) {
        if (error instanceof Error) {
          setError(error.message);
        }
        setStatus('standby');
      }
    },
    [doCreatePreviewShareUrl]
  );

  useEffect(() => {
    if (status === 'done' && inputRef.current) {
      inputRef.current.focus();
    }
  }, [status]);

  return { status, error, shareUrl, createShareUrl, setInputRef, standby };
}
