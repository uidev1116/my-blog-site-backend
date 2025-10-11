import { useState, useCallback, useRef } from 'react';
import HStack from '@components/stack/h-stack';
import Spinner from '@components/spinner/spinner';
import useGeocoding from '../hooks/useGeocoding';
import { notify } from '../../../../../../lib/notify';

interface GeoSearchProps {
  onSearch: (position: { lat: number; lng: number }) => void;
  placeholder?: string;
  buttonText?: string;
  className?: string;
  disabled?: boolean;
}

const GeoSearch = ({
  onSearch,
  placeholder = '住所、又はスポット名を入力してください',
  buttonText = '検索',
  className,
  disabled = false,
}: GeoSearchProps) => {
  const [isSearching, setIsSearching] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const { geocode } = useGeocoding();

  const search = useCallback(
    async (query: string) => {
      if (!query || disabled) {
        return;
      }

      setIsSearching(true);
      try {
        const result = await geocode(query);
        if (result) {
          onSearch(result);
        } else {
          notify.danger('該当する住所が見つかりませんでした');
        }
      } catch (error) {
        if (error instanceof Error) {
          notify.danger(error.message);
        } else {
          notify.danger('検索に失敗しました');
        }
      } finally {
        setIsSearching(false);
      }
    },
    [onSearch, disabled, geocode]
  );

  const handleClick = useCallback(() => {
    if (inputRef.current) {
      search(inputRef.current.value);
    }
  }, [search]);

  const handleKeyDown = useCallback(
    (event: React.KeyboardEvent) => {
      if (event.key === 'Enter' && !event.nativeEvent.isComposing) {
        event.preventDefault();
        if (inputRef.current) {
          search(inputRef.current.value);
        }
      }
    },
    [search]
  );

  return (
    <HStack className={className}>
      <input
        type="text"
        className="acms-admin-form-width-small"
        placeholder={placeholder}
        ref={inputRef}
        onKeyDown={handleKeyDown}
        disabled={disabled}
      />
      <button
        type="button"
        className="acms-admin-btn-admin"
        onClick={handleClick}
        disabled={disabled || isSearching}
        aria-busy={isSearching}
      >
        {isSearching && <Spinner size={10} borderWidth="2px" />}
        {buttonText}
      </button>
    </HStack>
  );
};

export default GeoSearch;
