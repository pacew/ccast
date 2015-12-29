var canvas;
var scene;
var camera;
var renderer;

var light;
var ambient_light;

var geometry;
var material;
var sphere;
var cube;


var then;

function render() {
    requestAnimationFrame(render);

    var now = Date.now();
    elapsed = now - then;

    if (!isNaN(elapsed)) {
	cube.rotation.y += elapsed / 500.0;
    }

    renderer.render(scene, camera);

    then = now;
}

function rapp_init () {
    $("body").html ("");

    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
    renderer = new THREE.CanvasRenderer();
    renderer.setSize(window.innerWidth, window.innerHeight);
    document.body.appendChild(renderer.domElement);

    canvas = $("canvas")[0];

    geometry = new THREE.SphereGeometry(1, 32, 32);
    material = new THREE.MeshLambertMaterial({color: 0xaa0000});
    sphere = new THREE.Mesh(geometry, material);
    sphere.position.set(0, 0, 20);
    scene.add(sphere);

    cube = new THREE.Mesh(new THREE.BoxGeometry(1, 1, 1), material);
    cube.position.set(10, 0, 10);
    scene.add(cube);

    camera.lookAt(new THREE.Vector3(0, 0, 20));
    scene.add(camera);

    light = new THREE.PointLight(0xffffff, 1, 1000);
    light.position.set(2, 2, 10);
    scene.add(light);

    ambient_light = new THREE.AmbientLight(0x222222);
    scene.add(ambient_light);

    render();
}

function rapp_rcv_msg (msg) {
    if (msg.val == "up") {
    } else if (msg.val == "down") {
    } else if (msg.val == "mousemove") {
//	camera.rotation.y = (.5 - msg.xpos) * Math.PI;
//	camera.rotation.x = -Math.PI + (.5 - msg.ypos) * Math.PI;
    } else if (msg.val == "reconnect") {
	connect ();
    } else {
	$("body").html (JSON.stringify (msg));
    }
}

$(function () {
    rapp_init ();
});
