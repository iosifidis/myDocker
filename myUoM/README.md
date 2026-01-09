# myUoM

The myUoM app is a project of the [Open Software Team](https://opensource.uom.gr) of Applied Informatics, University of Macedonia.
It was designed to facilitate students' daily interactions with the university.

The application is written in React and it is under MIT licence. The source code is available here [https://gitlab.com/opensourceuom/myUoM](https://gitlab.com/opensourceuom/myUoM)

## URL
The application is live. You can visit it here: 
[https://my.uom.gr/](https://my.uom.gr/)

## Instructions

Create the image locally (run the following command in the same folder as your Dockerfile):    
**docker built -t myapp .**

or

**docker build --no-cache -t myuom .**

You can run the image:   
**docker run --name myuom -d -p 80:80 myuom**

Then open your browser and type localhost.

You can stop the container using the command:   
**docker stop myuom**

You can start the container using the command:   
**docker start myuom**

## Docker hub

The image of this Dockerfile is here:  
https://hub.docker.com/r/iosifidis/myuom

To pull the image from the hub, run the command:  
**docker pull iosifidis/myuom**

Then, to run it, you can use the above commands.

## Upload to docker hub

```
docker login

docker tag myuom:latest iosifidis/myuom:latest

docker push iosifidis/myuom:latest
```
