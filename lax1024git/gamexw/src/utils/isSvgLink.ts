export default (link: string): boolean => {
  return /^[^.]*$/.test(link);
};
