date = new Date();
hour = date.getHours();
if (hour > 18) timeoftheday = "evening";
else if (hour > 12) timeoftheday = "afternoon";
else timeoftheday = "morning";
var greeting = ("Good " + timeoftheday + "!");
document.write(greeting);
