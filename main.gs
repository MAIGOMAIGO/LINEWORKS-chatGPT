function doPost(e) {
  // const text = e.postData.getDataAsString();
  const data = JSON.parse(e.postData.getDataAsString());
  const text = data.text;
  const sl = data.sl; // Source language
  const tl = data.tl; // translation language
  const translatedText = LanguageApp.translate(text, sl, tl);

  const output = ContentService.createTextOutput();
  output.setMimeType(ContentService.MimeType.TEXT);
  output.setContent(translatedText);
  return output;
}
