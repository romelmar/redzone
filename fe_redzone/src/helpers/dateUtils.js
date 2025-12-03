/**
 * Converts an ISO 8601 date string to a readable "Month day, year" format (e.g., "December 3, 2025").
 * @param {string} isoString The input date string.
 * @returns {string|null} The formatted date string, or null if the input is invalid.
 */
export function formatIsoToReadable(isoString) {
  if (!isoString) {
    return null;
  }

  const dateObject = new Date(isoString);

  // Check if the date object is valid before formatting
  if (isNaN(dateObject.getTime())) {
    console.error("Invalid date string provided:", isoString);
    return null;
  }

  const options = {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  };

  // 'en-US' locale ensures "Month day, year" order.
  return dateObject.toLocaleDateString('en-US', options);
}