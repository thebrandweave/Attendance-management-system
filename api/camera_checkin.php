<!DOCTYPE html>
<html>
<head>
  <title>Camera Check In</title>

  <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
</head>

<body>

<h2>Scan Employee ID Card</h2>

<video id="video" width="400" autoplay></video>
<br><br>

<button onclick="scan()">Check In</button>

<script>
const video = document.getElementById("video");

navigator.mediaDevices.getUserMedia({ video: true })
.then(stream => video.srcObject = stream);

function scan() {

  const canvas = document.createElement("canvas");
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;

  const ctx = canvas.getContext("2d");
  ctx.drawImage(video, 0, 0);

  const image = canvas.toDataURL("image/png");

  Tesseract.recognize(image, 'eng')
  .then(({ data: { text } }) => {

    console.log(text);

    const match = text.match(/EMP\d+/);

    if (!match) {
      alert("Employee ID not found ❌");
      return;
    }

    const empId = match[0];

    // send to your existing PHP file
    fetch("../api/checkin.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: "employee_id=" + empId
    })
    .then(res => res.text())
    .then(data => alert(data));

  });

}
</script>

</body>
</html>